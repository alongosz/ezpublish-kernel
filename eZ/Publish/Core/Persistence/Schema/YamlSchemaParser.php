<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Publish\Core\Persistence\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use eZ\Publish\Core\Base\Exceptions\InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

class YamlSchemaParser
{
    /**
     * Parse given Yaml custom schema file.
     *
     * @param $yamlSchemaFilePath
     *
     * @return \Doctrine\DBAL\Schema\Schema
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     */
    public function parseSchemaFile(string $yamlSchemaFilePath): Schema
    {
        if (!is_readable($yamlSchemaFilePath)) {
            throw new InvalidArgumentException('$yamlSchemaFilePath', "The file {$yamlSchemaFilePath} is not readable");
        }

        return $this->parseSchemaDescription(file_get_contents($yamlSchemaFilePath));
    }

    /**
     * Parse given Yaml string and return \Doctrine\DBAL\Schema.
     *
     * @param string $yamlSchemaDescription string containing schema described by custom Yaml format
     *
     * @return \Doctrine\DBAL\Schema\Schema
     */
    public function parseSchemaDescription(string $yamlSchemaDescription): Schema
    {
        $schemaDescription = Yaml::parse($yamlSchemaDescription);

        $schema = new Schema();
        foreach ($schemaDescription['tables'] as $tableName => $tableDescription) {
            $this->createTable($schema, $tableName, $tableDescription);
        }

        return $schema;
    }

    /**
     * Get Yaml Schema description for the given DBAL Schema.
     *
     * @param \Doctrine\DBAL\Schema\Schema $schema
     *
     * @return string Yaml Schema description
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getYamlSchema(Schema $schema)
    {
        $schemaDescription = ['tables' => []];

        foreach ($schema->getTables() as $table) {
            $tableName = $table->getName();
            $primaryKeyColumns = $table->hasPrimaryKey() ? $table->getPrimaryKeyColumns() : [];
            $schemaDescription[$tableName] = [
                'columns' => array_map(
                    function (Column $column) use ($primaryKeyColumns) {
                        $columnDetails = [
                            'type' => $column->getType()->getName(),
                            'primary' => in_array($column->getName(), $primaryKeyColumns),
                            'options' => [
                                'notnull' => $column->getNotnull(),
                                'length' => $column->getLength(),
                                'autoincrement' => $column->getAutoincrement(),
                                'default' => $column->getDefault(),
                            ],
                        ];

                        // unset unnecessary defaults to reduce output size
                        if (!$columnDetails['primary']) {
                            unset($columnDetails['primary']);
                        }
                        if (!$columnDetails['options']['autoincrement']) {
                            unset($columnDetails['options']['autoincrement']);
                        }
                        if (null === $columnDetails['options']['default']) {
                            unset($columnDetails['options']['default']);
                        }

                        return $columnDetails;
                    },
                    $table->getColumns()
                ),
                'indexes' => array_map(
                    function (Index $index) {
                        $indexDetails = [
                            'columns' => $index->getColumns(),
                            'unique' => $index->isUnique(),
                        ];

                        // unset unnecessary defaults to reduce output size
                        if (!$indexDetails['unique']) {
                            unset($indexDetails['unique']);
                        }

                        return $indexDetails;
                    },
                    $table->getIndexes()
                ),
            ];
            // unset primary index as it is handled per column
            unset($schemaDescription[$tableName]['indexes']['primary']);
            // unset indices if there are none (besides primary)
            if (empty($schemaDescription[$tableName]['indexes'])) {
                unset($schemaDescription[$tableName]['indexes']);
            }
        }

        return Yaml::dump($schemaDescription, 3);
    }

    /**
     * Create table based on schema description.
     *
     * @param \Doctrine\DBAL\Schema\Schema $schema
     * @param string $tableName
     * @param array $tableDescription
     * @return \Doctrine\DBAL\Schema\Table
     */
    protected function createTable(Schema $schema, $tableName, array $tableDescription)
    {
        $table = $schema->createTable($tableName);
        $primaryKeyColumns = [];
        foreach ($tableDescription['columns'] as $fieldName => $field) {
            $options = !empty($field['options']) && is_array($field['options'])
                ? $field['options']
                : [];

            $table->addColumn($fieldName, $field['type'], $options);
            if (!empty($field['primary'])) {
                $primaryKeyColumns[] = $fieldName;
            }
        }
        if (!empty($primaryKeyColumns)) {
            $table->setPrimaryKey($primaryKeyColumns);
        }
        if (!empty($tableDescription['indexes'])) {
            foreach ($tableDescription['indexes'] as $indexName => $index) {
                $options = [];
                // parse index length (supported by MySQL)
                if (!empty($index['length'])) {
                    $options['length'] = $index['length'];
                }
                // parse index function wrapper (supported by PostgreSQL)
                if (!empty($index['wrap_in'])) {
                    $options['wrap_in'] = $index['wrap_in'];
                }
                if (!empty($index['unique'])) {
                    $table->addUniqueIndex($index['columns'], $indexName, $options);
                } else {
                    $table->addIndex($index['columns'], $indexName, [], $options);
                }
            }
        }

        return $table;
    }
}
