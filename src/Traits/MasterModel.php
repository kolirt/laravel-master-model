<?php

namespace Kolirt\MasterModel\Traits;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
                if (
                    $relation instanceof \Illuminate\Database\Eloquent\Relations\HasOne
                ) {
                    $this->relations_to_save[] = [
                        'key' => $key,
                        'relation' => $relation,
                        'value' => $value
                    ];
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
            foreach ($this->relations_to_save as $relation) {
                /*dd(
                    $relation['relation']->getParentKey(),
                    $relation['relation']->getForeignKeyName(),
                    $relation['relation']->getLocalKeyName(),
                    $relation['relation']->getExistenceCompareKey(),
                    $relation['relation']->getQualifiedForeignKeyName(),
                    $relation['relation']->getQualifiedParentKeyName(),
                    $relation['value'],
                );*/

                /**
                 * Save HasOne relation
                 */
                if (
                    $relation['relation'] instanceof \Illuminate\Database\Eloquent\Relations\HasOne
                ) {
                    $parent = $relation['relation']->getParent();
                    if (is_null($relation['value'])) {
                        if ($parent->relationLoaded($relation['key'])) {
                            $parent->{$relation['key']}?->delete();
                        } else {
                            $relation['relation']->delete();
                        }
                    } else {
                        if (
                            $parent->relationLoaded($relation['key']) &&
                            $parent->{$relation['key']}
                        ) {
                            $parent->{$relation['key']}->update($relation['value']);
                        } else {
                            $relation_model = $relation['relation']->updateOrCreate(
                                [
                                    $relation['relation']->getForeignKeyName() => $relation['relation']->getParentKey()
                                ],
                                $relation['value']
                            );

                            $this->setRelation($relation['key'], $relation_model);
                        }
                    }
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
