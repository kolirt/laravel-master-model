<?php

namespace Kolirt\MasterModel;

use Illuminate\Database\Eloquent\Model as BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class Model extends BaseModel
{

    public $relationsToSave = [];


    /**
     * Delete the model from the database.
     *
     * @return bool|null
     *
     * @throws \Exception
     */
    public function delete()
    {
        foreach ($this->attributes as $key => $field) {
            if (in_array($key, $this->getFillable())) {
                try {
                    $path = str_replace(env('APP_URL'), '', $field);
                    if (file_exists(public_path($path)) && !is_dir(public_path($path)))
                        unlink(public_path() . $path);
                } catch (\Exception $e) {
                    Log::error($e);
                }
            }
        }

        return parent::delete();
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array $attributes
     * @return $this
     *
     * @throws \Illuminate\Database\Eloquent\MassAssignmentException
     */
    public function fill(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            if (in_array($key, $this->getFillable())) {
                if ($value instanceof UploadedFile) {
                    if ($value->isValid()) {
                        $imageName = randomName(time()) . '.' . $value->extension();
                        $value->move(public_path('/uploads/' . mb_strtolower(class_basename($this)) . '/'), $imageName);
                        $attributes[$key] = env('APP_URL') . '/uploads/' . mb_strtolower(class_basename($this)) . '/' . $imageName;

                        try {
                            $path = str_replace(env('APP_URL'), '', $this->$key);
                            if (file_exists(public_path($path)) && !is_dir(public_path($path)))
                                unlink(public_path() . $path);
                        } catch (\Exception $e) {
                            Log::error($e);
                        }
                    }
                }

                try {
                    $path = str_replace(env('APP_URL'), '', $this->$key);
                    if ($value != $this->$key && file_exists(public_path($path)) && $path) {
                        unlink(public_path() . $path);
                    }
                } catch (\Exception $e) {
                    Log::error($e);
                }
            }
        }

        $model = parent::fill(array_only($attributes, $this->fillable));

        foreach ($attributes as $key => $value) {
            if (!in_array($key, ['save']) && method_exists($this, $key)) {
                $relation = $model->$key();

                if ($relation instanceof HasMany) {
                    $this->relationsToSave[$key] = [$relation, $value];
                }
            }
        }

        return $model;
    }

    /**
     * Save the model to the database.
     *
     * @param  array $options
     * @return bool
     */
    public function save(array $options = [])
    {
        if ($this->relationsToSave) {
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

                        $relation->whereNotIn('id', $relation_ids)->delete();

                        if ($data !== null) {
                            $relation->createMany($data);
                        }
                    } else if ($relation instanceof BelongsToMany) {
                        $relation->sync([]);
                        if ($data !== null && is_array($data)) {
                            $relation->sync($data);
                        }
                    }
                }

                \DB::commit();
            } catch (\Exception $e) {
                \DB::rollBack();
                throw $e;
            }
        } else {
            $saved = parent::save($options);
        }

        return $saved;
    }

    public function isSoftDeletes()
    {
        return in_array('Illuminate\\Database\\Eloquent\\SoftDeletes', class_uses($this));
    }

}