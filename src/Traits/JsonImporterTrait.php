<?php

namespace Visiosoft\AdvsModule\Traits;

use Anomaly\Streams\Platform\Application\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

trait JsonImporterTrait
{
    //todo: slug rules

    /**
     * @rules array of rules
     * slug rules: ignore import, update slug on the new record then import, update exist record
     * ignore (default)
     * update_new_slug
     * update_exist_slug
     * unique rules: (default) ignore import, update exist record
     * ignore (default)
     * update exist unique
     */
    protected $definedImportRules = [
        'unique' => [
            'ignore',
            'update'
        ],
        'slug' => [
            'ignore',
            'update_new',
            'update_exist'
        ]
    ];

    /**
     * @param $sourcePath * addon source path
     * @param $sourceTableName * source table name that you want to import it
     * @param $savePath * temp folder patch saving changes to files in a temp folder. the folder must delete after import completed.
     * @param $rules * Which rules are set? type: (array), default:  ['unique' => 'ignore', 'slug' => 'ignore']
     * @param $reference * it is application name. default: null
     *
     *
     * @param array $relations * relation tables information. type: (array), example: [['relation_table_name' => 'column_name'], ....]
     * @return void
     */
    public function importDemoFromJson($sourcePath, $sourceTableName, $savePath, array $rules = [],
                                       $reference = null, array $relations = [])
    {
        $this->ruleValidation($rules);
        $uniquesColumns = [];
        $slugColumns = [];
        $data = $this->getTableData($sourcePath, $sourceTableName, $savePath);
        $tableDataFromTemp = $data['from_temp'];
        $data = $data['data'];
        $relationData = [];
        if (!empty($relations)) {
            foreach ($relations as $relation => $col) {
                $relationTableData = $this->getTableData($sourcePath, $relation, $savePath);
                $relationData[$relation]['from_temp'] = $relationTableData['from_temp'];
                $relationData[$relation]['data'] = $relationTableData['data'];
            }
        }
        $tableName = $this->getTableNameFromSourceTableName($sourceTableName);
        if (!empty($data)) {
            if (Schema::hasTable($tableName)) {
                $indexes = $this->getTableIndexes($tableName, $reference);
                if (!empty($indexes)) {
                    $uniquesColumns = $this->getUniqueColumns($indexes);
                }
                foreach ($data as $row) {
                    $ignore = false;
                    $id = $row['id'];
                    $insertedId = $id;
                    unset($row['id']);
                    if ($tableDataFromTemp) {
                        $selected = DB::table($tableName)->find($id);
                        if (!empty($selected)) {
                            $selected->update($row);
                        }
                    } else {
                        if (!empty($uniquesColumns)) {
                            foreach ($uniquesColumns as $uniqueColumn) {
                                $checkUnique = DB::table($tableName)->where($uniqueColumn, $row[$uniqueColumn])->first();
                                if (!empty($checkUnique)) {
                                    if ($rules['unique']['update']) {
                                        $checkUnique->update($row);
                                    } elseif ($rules['unique']['ignore']) {
                                        $ignore = true;
                                    }
                                } else {
                                    $insertedId = DB::table($tableName)->insertGetId($row);
                                    $row['id'] = $insertedId;
                                }
                            }
                        } else {
                            $insertedId = DB::table($tableName)->insertGetId($row);
                            $row['id'] = $insertedId;
                        }
                    }
                    if (!empty($relations) && !$ignore) {
                        foreach ($relations as $relation => $col) {
                            $relationTableName = $this->getTableNameFromSourceTableName($relation);
                            $indexes = $this->getTableIndexes($relationTableName, $reference);
                            if (!empty($indexes)) {
                                $uniquesColumns = $this->getUniqueColumns($indexes);
                            }
                            if (!empty($relationData[$relation]['data'])) {
                                $rows = $relationData[$relation]['data'];
                                $fromTemp = $relationData[$relation]['from_temp'];
                                $items = $rows->where($col, $id)->map(function ($item)
                                use (
                                    $insertedId, $relation, $sourcePath, $sourceTableName,
                                    $savePath, $fromTemp, $relationTableName, $uniquesColumns
                                ) {
                                    $item['entry_id'] = $insertedId;
                                    $itemData = $item;
                                    $insertedItemId = $itemData['id'];
                                    unset($itemData['id']);
                                    if (!empty($uniquesColumns)) {
                                        foreach ($uniquesColumns as $uniqueColumn) {
                                            $checkUnique = DB::table($relationTableName);
                                            foreach ($uniqueColumn as $unq) {
                                                $checkUnique = $checkUnique->where($unq, $item[$unq]);
                                            }
                                            $checkUnique = $checkUnique->first();
                                            if (!empty($checkUnique)) {
                                                $checkUnique->update($item);
                                            } else {
                                                if ($fromTemp) {
                                                    $selected = DB::table($relationTableName)->find($item['id']);
                                                    if (!empty($selected)) {
                                                        $selected->update($item);
                                                    }
                                                } else {
                                                    $insertedItemId = DB::table($relationTableName)->insertGetId($itemData);
                                                }
                                            }
                                        }
                                    } else {
                                        if ($fromTemp) {
                                            $selected = DB::table($relationTableName)->find($item['id']);
                                            if (!empty($selected)) {
                                                $selected->update($item);
                                            }
                                        } else {
                                            $insertedItemId = DB::table($relationTableName)->insertGetId($itemData);
                                        }
                                    }
                                    $item['id'] = $insertedItemId;
                                    return $item;
                                });
                                $relationData[$relation]['data'] = $rows->replace($items->all());
                            }
                        }
                    }
                    $ignore = false;
                    $data->replace($row);
                }
            } else {
                throw new \Exception('Table `' . $tableName . '` not found!', 404);
            }
        }
        Storage::put($savePath . $reference . '_' . $tableName . '.json', $data);
        if (!empty($relations) && !empty($relationData)) {
            foreach ($relations as $relation => $col) {
                if (!empty($relationData[$relation]['data'])) {
                    Storage::put($savePath . $relation . '.json', $relationData[$relation]['data']);
                }
            }
        }
    }

    /**
     * @param $rules
     * @return void
     * @throws \Exception
     */
    public function ruleValidation($rules)
    {
        foreach ($this->definedImportRules as $key => $definedRule) {
            if (empty($rules[$key])) {
                throw new \Exception('importer rule for ' . $key . ' columns is invalid');
            }
            if (in_array($rules[$key], $this->definedImportRules)) {
                throw new \Exception('importer rule for ' . $key . ' columns is invalid');
            }
        }
    }

    /**
     * @param $sourceTableName
     * @return string
     */
    public function getTableNameFromSourceTableName($sourceTableName)
    {
        $exploded = explode('_', $sourceTableName);
        unset($exploded[0]);
        return implode($exploded, '_');
    }

    /**
     * @param $sourcePath
     * @param $sourceTableName
     * @param $savePath
     * @return array
     */
    public function getTableData($sourcePath, $sourceTableName, $savePath)
    {
        $appReference = app(Application::class)->getReference() . "_";
        $imploded = $this->getTableNameFromSourceTableName($sourceTableName);
        $tableName = $appReference . $imploded;
        $filePath = $sourcePath . $tableName . '.json';
        $fromTemp = false;
        $savedFilePath = storage_path('streams/' . app(Application::class)->getReference() . '/files-module/local' . $savePath . $tableName . '.json');
        if (file_exists($savedFilePath)) {
            $fromTemp = true;
            $filePath = $savedFilePath;
        }
        $json = json_decode(file_get_contents($filePath), true);
        return ['data' => collect($json), 'from_temp' => $fromTemp];
    }

    /**
     * @param $tableName
     * @param $reference
     * @return array|mixed
     */
    public function getTableIndexes($tableName, $reference = null)
    {
        $table = $tableName;
        if (!empty($reference)) {
            $table = $reference . '_' . $tableName;
        }
        $indexes = DB::select('SHOW INDEX FROM ' . $table . ';');
        if (!empty($indexes)) {
            $indexes = json_decode(json_encode($indexes), true);
        }
        return $indexes;
    }

    /**
     * @param $indexes
     * @return array
     */
    public function getUniqueColumns($indexes)
    {
        $columns = [];
        foreach ($indexes as $index) {
            if (!$index['Non_unique'] && $index['Column_name'] != 'id') {
                $columns[$index['Key_name']][] = $index['Column_name'];
            }
        }
        return $columns;
    }
}