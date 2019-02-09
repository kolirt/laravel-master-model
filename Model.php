<?php

namespace Kolirt\MasterModel;

use Illuminate\Database\Eloquent\Model as BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class Model extends BaseModel
{

    public $relationsToSave = [];

    public function fill(array $attributes)
    {

        foreach ($attributes as $key => $value) {
            if ($value instanceof UploadedFile) {
                if (in_array($key, $this->getFillable())) {
                    if ($value->isValid()) {
                        $imageName = randomName(time()) . '.' . $value->extension();
                        $value->move(public_path('/uploads/' . mb_strtolower(class_basename($this)) . '/'), $imageName);
                        $attributes[$key] = '/uploads/' . mb_strtolower(class_basename($this)) . '/' . $imageName;
                    }
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

        if ($saved) $this->fireModelEvent('saved', false);

        return $saved;
    }

}