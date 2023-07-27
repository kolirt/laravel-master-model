<?php

namespace Kolirt\MasterModel;

use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\File\UploadedFile;

trait MasterModel
{

    public $relationsToSave = [];
    public $priorityRelationsToSave = [];
    public $files = [];

    /**
     * Delete the model from the database.
     *
     * @return bool|null
     *
     * @throws \Exception
     */
    public function delete()
    {
        $delete = false;

        if ($this->isSoftDeletes()) {
            if ($this->trashed())
                $delete = true;
        } else {
            $delete = true;
        }

        if ($delete) {
            foreach ($this->attributes as $key => $field) {
                if (in_array($key, $this->getFillable())) {
                    try {
                        $path = $field;
                        if (file_exists(storage_path($path)) && !is_dir(storage_path($path)))
                            unlink(storage_path() . $path);
                    } catch (\Exception $e) {
                        Log::error($e);
                    }
                }
            }
        }

        return parent::delete();
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param array $attributes
     * @return $this
     *
     * @throws MassAssignmentException
     */
    public function fill(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            if (in_array($key, $this->getFillable())) {
                if ($value instanceof UploadedFile) {
                    if ($value->isValid()) {
                        $dir_path = '/app/public/uploads/' . mb_strtolower(class_basename($this)) . '/';

                        $dir = storage_path($dir_path);
                        if (!is_dir($dir)) {
                            mkdir($dir, 0755, true);
                        }

                        $imageName = uniqid(time()) . '.' . $value->extension();
                        $value->move($dir, $imageName);
                        $file_path = $dir_path . $imageName;
                        $attributes[$key] = $file_path;
                        $this->files[] = $file_path;

                        try {
                            $path = $this->$key;
                            if (file_exists(storage_path($path)) && !is_dir(storage_path($path))) {
                                unlink(storage_path() . $path);
                            }
                        } catch (\Exception $e) {
                            Log::error($e);
                        }
                    }
                }

                try {
                    $path = $this->$key;
                    if ($value != $this->$key && file_exists(storage_path($path)) && $path) {
                        unlink(storage_path() . $path);
                    }
                } catch (\Exception $e) {
                    Log::error($e);
                }
            }
        }

        $newAttributes = array_only($attributes, $this->fillable);

        foreach ($attributes as $key => $value) {

            if (!in_array($key, ['save']) && method_exists($this, $key)) {
                $relation = $this->$key();

                if ($relation instanceof HasMany || $relation instanceof MorphToMany || $relation instanceof BelongsToMany || $relation instanceof HasOne) {
                    $this->relationsToSave[$key] = [$relation, $value];
                } else if ($relation instanceof BelongsTo) {
                    $foreignKeyName = $relation->getForeignKeyName();
                    if (is_object($value)) {
                        if (method_exists($value, 'getKey')) {
                            $newAttributes[$foreignKeyName] = $value->getKey();
                        }
                    } else {
                        $newAttributes[$foreignKeyName] = $value;
                    }
                }
            }
        }

        $model = parent::fill($newAttributes);

        return $model;
    }

    /**
     * Save the model to the database.
     *
     * @param array $options
     * @return bool
     * @throws \Exception
     */
    public function save(array $options = [])
    {
        try {
            \DB::beginTransaction();

            $saved = parent::save($options);

            $relationsToSave = $this->relationsToSave;
            $this->relationsToSave = [];

            foreach ($relationsToSave as $key => $rel) {
                $relation = $rel[0];
                $data = $rel[1];

                if ($relation instanceof HasMany) {
                    $relation_ids = array_pluck($data ?: [], 'id');

                    foreach ($data ?? [] as $i => $datum) {
                        if (isset($datum['id']) && $datum['id']) {
                            $related = $relation->getRelated()->where('id', $datum['id'])->first();

                            if ($related) {
                                $related->update($datum);
                                unset($data[$i]);
                            }
                        }
                    }

                    $relation->whereNotIn('id', array_filter($relation_ids))->delete();

                    if ($data !== null) {
                        $relation->createMany($data);
                    }
                } else if ($relation instanceof BelongsToMany || $relation instanceof MorphToMany || $relation instanceof BelongsToMany) {
                    $relation->sync([]);
                    if ($data !== null && is_array($data)) {
                        $relation->sync($data);
                    }
                } else if ($relation instanceof HasOne) {
                    $localKeyName = $relation->getLocalKeyName();
                    if (isset($data[$localKeyName]) && $data[$localKeyName]) {
                        $related = $relation->getRelated()->where($localKeyName, $data[$localKeyName])->first();
                        if ($related) {
                            unset($data[$localKeyName]);
                            $related->update($data);
                        }
                    } else {
                        $relation->create($data);
                    }
                }
            }

            \DB::commit();
        } catch (\Exception $e) {
            foreach ($this->files as $file) {
                try {
                    $path = $file;
                    if (file_exists(storage_path($path)) && !is_dir(storage_path($path))) {
                        unlink(storage_path() . $path);
                    }
                } catch (\Exception $e) {
                    Log::error($e);
                }
            }
            \DB::rollBack();
            throw $e;
        }

        return $saved;
    }

    public function isSoftDeletes()
    {
        return in_array('Illuminate\\Database\\Eloquent\\SoftDeletes', class_uses($this));
    }

}
