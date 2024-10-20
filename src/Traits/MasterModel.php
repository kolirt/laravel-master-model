<?php

namespace Kolirt\MasterModel\Traits;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait MasterModel
{

    protected array $relations_to_save = [];
    protected array $saved_files = [];
    protected array $files_to_delete = [];

    public function delete(): ?bool
    {
        $need_delete = false;

        if (
            in_array(SoftDeletes::class, class_uses($this))
        ) {
            if ($this->trashed()) {
                $need_delete = true;
            }
        } else {
            $need_delete = true;
        }

        if ($need_delete) {
            /**
             * Prepare files to delete
             */
            foreach ($this->fillableFromArray($this->getAttributes()) as $value) {
                if (is_stored_file($value)) {
                    [$disk_name, $stored_file_path] = explode(':', $value);
                    if (Storage::disk($disk_name)->exists($stored_file_path)) {
                        $this->files_to_delete[] = [
                            'disk' => $disk_name,
                            'value' => $stored_file_path
                        ];
                    }
                }
            }

            /**
             * Prepare files to delete from relations
             */
            foreach ($this->getRelations() as $relation => $relation_model) {
                $relation = $this->$relation();

                if (
                    $relation instanceof \Illuminate\Database\Eloquent\Relations\HasOne
                ) {
                    foreach ($relation_model->fillableFromArray($relation_model->getAttributes()) as $key => $value) {
                        if (is_stored_file($value)) {
                            [$disk_name, $stored_file_path] = explode(':', $value);
                            if (Storage::disk($disk_name)->exists($stored_file_path)) {
                                $this->files_to_delete[] = [
                                    'disk' => $disk_name,
                                    'value' => $stored_file_path
                                ];
                            }
                        }
                    }
                }
            }
        }

        $deleted = parent::delete();

        if ($deleted) {
            if ($need_delete) {
                /**
                 * Delete files
                 */
                foreach ($this->files_to_delete as $file) {
                    Storage::disk($file['disk'])->delete($file['value']);
                }
            }
        }

        return $deleted;
    }

    public function fill(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            if ($this->isRelation($key)) {
                $relation = $this->$key();

                switch (true) {
                    case $relation instanceof \Illuminate\Database\Eloquent\Relations\HasOne:
                    case $relation instanceof \Illuminate\Database\Eloquent\Relations\MorphOne:
                    case $relation instanceof \Illuminate\Database\Eloquent\Relations\HasMany:
                    case $relation instanceof \Illuminate\Database\Eloquent\Relations\MorphMany:
                        $this->relations_to_save[] = [
                            'relation_name' => $key,
                            'relation' => $relation,
                            'value' => $value
                        ];
                        unset($attributes[$key]);
                        break;
                }
            }
        }

        return parent::fill($attributes);
    }

    public function save(array $options = [])
    {

        foreach ($this->fillableFromArray($this->getAttributes()) as $key => $value) {
            $origin_value = $this->getOriginal($key);

            if ($value instanceof UploadedFile) {
                if ($value->isValid()) {
                    $disk_name = $this->getDisk($key);

                    $stored_file_path = $value->store($this->getUploadFolder($key), $disk_name);
                    $this->setAttribute($key, "$disk_name:$stored_file_path");
                    $this->saved_files[] = [
                        'key' => $key,
                        'disk' => $disk_name,
                        'value' => $stored_file_path,
                        'old_value' => $origin_value
                    ];
                }
            }

            if ($value != $origin_value) {
                if (is_stored_file($origin_value)) {
                    [$origin_disk_name, $origin_value] = explode(':', $origin_value);
                    if (Storage::disk($origin_disk_name)->exists($origin_value)) {
                        $this->files_to_delete[] = [
                            'disk' => $origin_disk_name,
                            'value' => $origin_value
                        ];
                    }
                }
            }
        }

        $saved = parent::save($options);

        if ($saved) {
            /**
             * Delete files
             */
            foreach ($this->files_to_delete as $file) {
                Storage::disk($file['disk'])->delete($file['value']);
            }

            /**
             * Save relations
             */
            foreach ($this->relations_to_save as $relation_to_save) {
                [
                    'relation_name' => $relation_name,
                    'relation' => $relation,
                    'value' => $value,
                ] = $relation_to_save;

                switch (true) {
                    /**
                     * Save HasOne relation
                     * Save MorphOne relation
                     */
                    case $relation instanceof \Illuminate\Database\Eloquent\Relations\HasOne:
                    case $relation instanceof \Illuminate\Database\Eloquent\Relations\MorphOne:
                        $parent = $relation->getParent();

                        if (is_null($value)) {
                            if (
                                $parent->relationLoaded($relation_name) &&
                                $parent->getRelation($relation_name)
                            ) {
                                $parent->getRelation($relation_name)->delete();
                                $this->unsetRelation($relation_name);
                            } else {
                                $relation->delete();
                            }
                        } else {
                            if (
                                $parent->relationLoaded($relation_name) &&
                                $parent->getRelation($relation_name)
                            ) {
                                $parent->getRelation($relation_name)->update($value);
                            } else {
                                $relation_model = $relation->first();

                                if ($relation_model) {
                                    $relation_model->update($value);
                                } else {
                                    $relation_model = $relation->create($value);
                                }

                                $this->setRelation($relation_name, $relation_model);
                            }
                        }

                        break;

                    /**
                     * Save HasMany relation
                     * Save MorphMany relation
                     */
                    case $relation instanceof \Illuminate\Database\Eloquent\Relations\HasMany:
                    case $relation instanceof \Illuminate\Database\Eloquent\Relations\MorphMany:
                        $mode = $value['mode'] ?? null;
                        $value = key_exists('value', $value) ? $value['value'] : $value;

                        $parent = $relation->getParent();

                        if (is_null($value)) {
                            if ($mode === 'sync') {
                                if (
                                    $parent->relationLoaded($relation_name) &&
                                    $parent->getRelation($relation_name)
                                ) {
                                    $parent->getRelation($relation_name)->each->delete();
                                    $this->unsetRelation($relation_name);
                                } else {
                                    $relation->delete();
                                }
                            }
                        } else {
                            $loaded_relations = match (true) {
                                $parent->relationLoaded($relation_name) => $parent->getRelation($relation_name),
                                $mode === 'sync' => $relation->get(),
                                default => collect()
                            };

                            $values_to_update = array_filter($value, fn($item) => isset($item[$relation->getLocalKeyName()]));
                            $new_values = array_filter($value, fn($item) => !isset($item[$relation->getLocalKeyName()]));

                            $updated_items = collect();

                            if (count($values_to_update)) {
                                foreach ($values_to_update as $value_to_update) {
                                    $relation_model = $loaded_relations->firstWhere($relation->getLocalKeyName(), $value_to_update[$relation->getLocalKeyName()]);

                                    if (!$relation_model) {
                                        $relation_model = $relation
                                            ->where($relation->getLocalKeyName(), $value_to_update[$relation->getLocalKeyName()])
                                            ->first();
                                    }

                                    unset($value_to_update[$relation->getLocalKeyName()]);

                                    if ($relation_model) {
                                        $relation_model->update($value_to_update);
                                        $updated_items[] = $relation_model;
                                    } else {
                                        $new_values[] = $value_to_update;
                                    }
                                }
                            }

                            if (count($new_values)) {
                                $items = $relation->createMany($new_values);
                                $loaded_relations->push(...$items);
                                $updated_items->push(...$items);
                            }

                            if ($mode === 'sync') {
                                $updated_items_keyed = collect($updated_items)->keyBy($relation->getLocalKeyName());

                                $relations_to_delete = $loaded_relations->filter(function ($item) use ($relation, $updated_items_keyed) {
                                    return !$updated_items_keyed->has($item->getAttribute($relation->getLocalKeyName()));
                                });
                                $relations_to_delete->each->delete();

                                $loaded_relations = $updated_items;
                            }

                            $this->setRelation($relation_name, $loaded_relations);
                        }

                        break;
                }
            }
        } else {
            /**
             * Rollback saved files
             */
            foreach ($this->saved_files as $file) {
                Storage::disk($file['disk'])->delete($file['value']);
                $this->setAttribute($file['key'], $file['old_value']);
            }
        }

        $this->relations_to_save = [];
        $this->saved_files = [];
        $this->files_to_delete = [];

        return $saved;
    }

    private function getUploadFolder($key): string
    {
        return collect([
            config('master-model.files.folder'),
            $this->upload_model_folder,
            $this->upload_folders[$key] ?? null
        ])->filter()->implode('/');
    }

    private function getDisk($key = null): string
    {
        return $this->upload_disks[$key] ?? config('master-model.files.disk');
    }

    /*private function hasUploadedFile(array $attributes): bool
    {
        foreach ($attributes as $value) {
            if ($value instanceof UploadedFile) {
                return true;
            }
        }
        return false;
    }*/

    public function getImage()
    {

    }

}
