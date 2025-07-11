<?php
namespace Raxon\Doctrine\Module;

use Exception;
use Raxon\App;
use Raxon\Exception\FileWriteException;
use Raxon\Module\Controller;
use Raxon\Module\Data;
use Raxon\Module\Dir;
use Raxon\Module\File;

class Build {

    const FILE = __FILE__;

    /**
     * @throws FileWriteException
     * @throws Exception
     */
    public static function entity(App $object, string $class, object $role, object|array $node, array $options=[]): void
    {        
        if (is_object($node)) {
            $node_class = get_class($node);
            switch ($node_class) {
                case 'stdClass':
                    $node = new Data($node);
                    break;
                default:
                    throw new Exception('unknown node class: ' . $node_class);
            }
        } elseif (is_array($node)) {
            $node = new Data($node);
        } else {
            return;
        }        
        if ($node->has('table')) {
            $table = $node->get('table');
            $entity = $node->get('entity');
            $target_dir = $object->config('project.dir.source') .
                'Entity' .
                $object->config('ds')
            ;
            $target = $target_dir .
                $entity .
                $object->config('extension.php')
            ;
            $data = [];
            $data[] = '<?php';
            $data[] = '';
            $data[] = 'namespace Entity;';
            $columns = $node->get('column');
            $data_columns = [];
            $data_functions = [];
            $encrypted = [];
            $construct = [];
            $is_uuid = false;
            $is_updated = false;
            $is_created = false;
            $is_deleted = false;
            $type_is_created = null;
            $type_is_updated = null;
            $type_is_deleted = null;
            $use_node = false;
            if ($columns && is_array($columns)) {
                foreach ($columns as $nr => $column) {
                    $is_set = true;
                    $is_get = true;
                    $is_both = true;
                    $is_encrypted = false;
                    $is_null = false;
                    $options_default = null;
                    $options_default_value = null;
                    $options_unsigned = null;
                    if ($column->name === 'uuid') {
                        $is_uuid = true;
                    }
                    if ($column->name === 'isCreated') {
                        $is_created = true;
                    }
                    if ($column->name === 'isUpdated') {
                        $is_updated = true;
                    }
                    if ($column->name === 'isDeleted') {
                        $is_deleted = true;
                    }
                    if (
                        property_exists($column, 'options') &&
                        property_exists($column->options, 'id') &&
                        $column->options->id === true
                    ) {
                        $data_columns[] = '#[ORM\Id]';
                        $is_set = false;
                        $is_both = false;
                        $is_null = true;
                    }
                    if(
                        property_exists($column, 'options') &&
                        property_exists($column->options, 'nullable')
                    ){
                        $is_null = $column->options->nullable;
                    }
                    if(
                        property_exists($column, 'type') &&
                        $column->type === 'one-to-one'
                    ){
                        if(
                            property_exists($column, 'options') &&
                            property_exists($column->options, 'join') &&
                            property_exists($column->options->join, 'column') &&
                            is_array($column->options->join->column) &&
                            array_key_exists(0, $column->options->join->column) &&
                            property_exists($column->options, 'target') &&
                            property_exists($column->options->target, 'entity')
                        ){
                            $column_join = $column->options->join->column[0];
                            $data_columns[] = '#[ORM\OneToOne(';
                            $data_columns_parameters = [];
                            $data_columns_parameters[] = 'targetEntity: "' . $column->options->target->entity .'"';
                            if(
                                property_exists($column->options, 'inversed') &&
                                property_exists($column->options->inversed, 'by')
                            ){
                                $data_columns_parameters[] = '    inversedBy: "' . $column->options->inversed->by .'"';
                            }
                            if(property_exists($column->options, 'cascade')){
                                $data_columns_parameters[] = 'cascade: ["' . implode('", "', $column->options->cascade) . '"]';
                            }
                            $data_columns[] = '    ' . implode(',' . PHP_EOL . '        ', $data_columns_parameters);
                            $data_columns[] = ')]';
                            $data_columns[] = '#[ORM\JoinColumn(';
                            $data_columns_parameters = [];
                            $data_columns_parameters[] = 'name: "' . $column_join->name . '"';
                            if(property_exists($column_join, 'reference')){
                                $data_columns_parameters[] = 'referencedColumnName: "' . $column_join->reference . '"';
                            }
                            if(property_exists($column_join, 'nullable')){
                                $data_columns_parameters[] = 'nullable: ' . ($column_join->nullable === true ? 'true' : 'false');
                            }
                            $data_columns[] = '    ' . implode(',' . PHP_EOL . '    ', $data_columns_parameters);
                            $data_columns[] = ')]';
                        }
                        elseif(
                            property_exists($column, 'options') &&
                            property_exists($column->options, 'mapped') &&
                            property_exists($column->options->mapped, 'by') &&
                            property_exists($column->options, 'target') &&
                            property_exists($column->options->target, 'entity')
                        ){
                            //#[OneToOne(targetEntity: Cart::class, mappedBy: 'customer')]
                            $data_columns[] = '#[ORM\OneToOne(';
                            $data_columns_parameters = [];
                            $data_columns_parameters[] = 'targetEntity: "' . $column->options->target->entity .'"';
                            $data_columns_parameters[] = 'mappedBy: "' . $column->options->mapped->by .'"';
                            $data_columns[] = '    ' . implode(',' . PHP_EOL . '        ', $data_columns_parameters);
                            $data_columns[] = ')]';
                        }
                        else {
                            d($column);
                            continue;
                        }
                        /*
                        $data_columns[] = '#[ORM\OneToOne(' .
                            PHP_EOL .
                            '        ' .
                            implode(
                                ', ' .
                                PHP_EOL .
                                '        '
                                ,
                                $column_value
                            ) .
                            PHP_EOL .
                            '    ' .
                            ')]';
                        */
                    }
                    elseif(
                        property_exists($column, 'type') &&
                        $column->type === 'many-to-many'
                    ){
                        if(
                            property_exists($column, 'options') &&
                            (
                            (
                                property_exists($column->options, 'inversed') &&
                                property_exists($column->options->inversed, 'by')
                            ) ||
                            (
                                property_exists($column->options, 'mapped') &&
                                property_exists($column->options->mapped, 'by')
                            )
                            ) &&
                            property_exists($column->options, 'target') &&
                            property_exists($column->options->target, 'entity')
                        ){
                            //#[OneToOne(targetEntity: Cart::class, inversedBy: 'customer')]
                            //#[ManyToMany(targetEntity: "Extension", inversedBy: "applications", cascade: ["persist"])]
                            $data_columns[] = '#[ORM\ManyToMany(';
                            $data_columns_parameters = [];
                            $data_columns_parameters[] = 'targetEntity: "' . $column->options->target->entity .'"';
                            if(property_exists($column->options, 'inversed')){
                                $data_columns_parameters[] = 'inversedBy: "' . $column->options->inversed->by .'"';
                            }
                            if(property_exists($column->options, 'mapped')){
                                $data_columns_parameters[] = 'mappedBy: "' . $column->options->mapped->by .'"';
                            }
                            if(property_exists($column->options, 'cascade')){
                                $data_columns_parameters[] = 'cascade: ["' . implode('", "', $column->options->cascade) . '"]';
                            }
                            $data_columns[] = '    ' . implode(',' . PHP_EOL . '        ', $data_columns_parameters);
                            $data_columns[] = ')]';
                        }
                    }
                    elseif (
                        property_exists($column, 'type')
                    ) {
                        $column_value = [];
                        $column_value[] = 'name: "`' . $column->name . '`"';
                        $column_value[] = 'type: "' . $column->type . '"';

                        if (
                            property_exists($column, 'options') &&
                            property_exists($column->options, 'unique') &&
                            $column->options->unique === true
                        ) {
                            $column_value[] = 'unique: true';
                        }
                        if (
                            property_exists($column, 'options') &&
                            property_exists($column->options, 'nullable') &&
                            $column->options->nullable === true
                        ) {
                            $column_value[] = 'nullable: true';
                            $is_null = true;
                        }
                        if (
                            property_exists($column, 'options') &&
                            property_exists($column->options, 'length') &&
                            is_numeric($column->options->length)
                        ) {
                            $options_length = $column->options->length + 0;
                            $column_value[] = 'length: ' . $options_length;
                        }
                        if (
                            property_exists($column, 'options') &&
                            property_exists($column->options, 'precision') &&
                            is_numeric($column->options->precision)
                        ) {
                            $options_precision = $column->options->precision + 0;
                            $column_value[] = 'precision: ' . $options_precision;
                        }
                        if (
                            property_exists($column, 'options') &&
                            property_exists($column->options, 'scale') &&
                            is_numeric($column->options->scale)
                        ) {
                            $options_scale = $column->options->scale + 0;
                            $column_value[] = 'scale: ' . $options_scale;
                        }
                        if (
                            property_exists($column, 'options') &&
                            property_exists($column->options, 'default')
                        ) {
                            if ($column->options->default !== null) {
                                if (is_numeric($column->options->default)) {
                                    $options_default_value = $column->options->default + 0;
                                    $options_default = '"default" => ' . $options_default_value;
                                } elseif (is_bool($column->options->default)) {
                                    $options_default_value = (bool)$column->options->default;
                                    if ($options_default_value === true) {
                                        $options_default = '"default" => true';
                                    } else {
                                        $options_default = '"default" => false';
                                    }
                                } else {
                                    $options_default_value = $column->options->default;
                                    $options_default = '"default" => "' . $options_default_value . '"';
                                }
                            }
                        }
                        if (
                            property_exists($column, 'options') &&
                            property_exists($column->options, 'unsigned')
                        ) {
                            if (
                                is_bool($column->options->unsigned) &&
                                $column->options->unsigned === true
                            ) {
                                $options_unsigned = '"unsigned" => true';
                            }
                        }
                        if (
                            property_exists($column, 'options') &&
                            property_exists($column->options, 'definition') &&
                            is_string($column->options->definition)
                        ) {
                            $column_value[] = 'columnDefinition: "' . $column->options->definition . '"';
                        }
                        $options_all = [];
                        if ($options_unsigned) {
                            $options_all[] = $options_unsigned;
                        }
                        if ($options_default) {
                            $options_all[] = $options_default;
                        }
                        if (array_key_exists(0, $options_all)) {
                            $column_value[] = 'options: [' . implode(', ', $options_all) . ']';
                        }
                        $data_columns[] = '#[ORM\Column(' .
                            PHP_EOL .
                            '        ' .
                            implode(
                                ', ' .
                                PHP_EOL .
                                '        '
                                ,
                                $column_value
                            ) .
                            PHP_EOL .
                            '    ' .
                            ')]';
                    }
                    if(
                        property_exists($column, 'options') &&
                        property_exists($column->options, 'join') &&
                        property_exists($column->options->join, 'table')
                    ){
                        $data_columns[] = '#[ORM\JoinTable(';
                        $data_columns_parameters = [];
                        $data_columns_parameters[] = 'name: "' . $column->options->join->table . '"';
                        if(property_exists($column->options->join, 'join')){
                            $data_columns_parameters[] = 'joinColumns: [' . PHP_EOL . '        ' . 'new JoinColumn(';
                            $data_columns_parameters_join = [];
                            $data_columns_parameters_join[] = 'name: "' . $column->options->join->join->name . '"';
                            $data_columns_parameters_join[] = 'referencedColumnName: "' . $column->options->join->join->reference . '"';
                            $data_columns_parameters_join[] = 'nullable: ' . ($column->options->join->join->nullable === true ? 'true' : 'false');
                            $data_columns_parameters[] = '            ' . implode(',' . PHP_EOL . '            ', $data_columns_parameters_join);
                            $data_columns_parameters[] = '        ' . ')]';
                        }
                        if(property_exists($column->options->join, 'inverse')){
                            $data_columns_parameters[] = 'inverseJoinColumns: [' . PHP_EOL . '        ' . 'new JoinColumn(';
                            $data_columns_parameters_inverse = [];
                            $data_columns_parameters_inverse[] = 'name: "' . $column->options->join->inverse->name . '"';
                            $data_columns_parameters_inverse[] = 'referencedColumnName: "' . $column->options->join->inverse->reference . '"';
                            $data_columns_parameters_inverse[] = 'nullable: ' . ($column->options->join->inverse->nullable === true ? 'true' : 'false');
                            $data_columns_parameters[] = '            ' . implode(',' . PHP_EOL . '            ', $data_columns_parameters_inverse);
                            $data_columns_parameters[] = '        ' . ')]';
                        }
                        $data_columns[] = '    ' . implode(',' . PHP_EOL . '    ', $data_columns_parameters);
                        $data_columns[] = ')]';
                    }
                    if (
                        property_exists($column, 'options') &&
                        property_exists($column->options, 'autoincrement')
                    ) {
                        if (is_string($column->options->autoincrement)) {
                            $data_columns[] = '#[ORM\GeneratedValue(strategy: "' . mb_strtoupper($column->options->autoincrement) . '")]';
                        } elseif (
                            is_bool($column->options->autoincrement) &&
                            $column->options->autoincrement === true
                        ) {
                            $data_columns[] = '#[ORM\GeneratedValue(strategy: "AUTO")]';
                        }
                    }
                    $type = null;
                    switch ($column->type) {
                        case 'smallint':
                        case 'integer':
                            $type = 'int';
                            break;
                        case 'double':
                        case 'float':
                            $type = 'float';
                            break;
                        case 'array':
                        case 'simple_array':
                            $type = 'array';
                            break;
                        case 'object':
                            $type = 'object';
                            break;
                        case 'time':
                        case 'date':
                        case 'datetime':
                        case 'datetimetz':
                            $type = 'DateTime';
                            break;
                        case 'time_immutable':
                        case 'date_immutable':
                        case 'datetimetz_immutable':
                            $type = 'DateTimeImmutable';
                            break;
                        case 'dateinterval':
                            $type = 'DateInterval';
                            break;
                        case 'decimal':
                        case 'bigint':
                        case 'text':
                        case 'ascii_string':
                        case 'varchar':
                        case 'guid':
                        case 'string':
                            $type = 'string';
                            break;
                        case 'blob':
                        case 'binary':
                            $type = 'resource';
                            break;
                        case 'json':
                            $type = 'mixed';
                            break;
                        case 'boolean':
                            $type = 'bool';
                            break;
                        default :
                            $type = 'mixed';
                            break;
                    }
                    if ($type_is_created === null && $is_created) {
                        $type_is_created = $type;
                    }
                    if ($type_is_updated === null && $is_updated) {
                        $type_is_updated = $type;
                    }
                    if ($type_is_deleted === null && $is_deleted) {
                        $type_is_deleted = $type;
                    }
                    if ($type === 'mixed') {
                        $return_type = $type;
                    } else if ($is_null) {
                        $return_type = '?' . $type;
                        $type .= '|null';
                    } else {
                        $return_type = $type;
                    }
                    if (
                        property_exists($column, 'options') &&
                        property_exists($column->options, 'encryption') &&
                        $column->options->encryption === true
                    ) {
                        $encrypted[] = $column->name;
                        $is_encrypted = true;
                    }
                    if ($is_both) {
                        $both = [];
                        if ($is_encrypted) {
                            $both[] = '/**';
                            $both[] = '* @throws FileWriteException';
                            $both[] = '* @throws WrongKeyOrModifiedCiphertextException';
                            $both[] = '* @throws BadFormatException';
                            $both[] = '* @throws EnvironmentIsBrokenException';
                            $both[] = '* @throws Exception';
                            $both[] = '*/';
                        }
                        if(str_contains($type, 'null')){
                            $both_type = $type;
                        } else if($type !== 'mixed') {
                            $both_type = $type . '|null';
                        } else {
                            $both_type = $type;
                        }
                        $both[] = 'public function ' . str_replace('.', '', lcfirst(Controller::name($column->name))) . '(' . $both_type . ' $' . $column->name . '=null): ' . $return_type;
                        $both[] = '{';
                        $both[] = '    if($' . $column->name . ' !== null){';
                        $both[] = '        $this->set' . str_replace('.', '', Controller::name($column->name)) . '($' . $column->name . ');';
                        $both[] = '    }';
                        $both[] = '    return $this->get' . str_replace('.', '', Controller::name($column->name)) . '();';
                        $both[] = '}';
                        $data_functions[] = $both;
                    }
                    if ($is_null) {
                        if ($type === 'mixed') {
                            $data_columns[] = 'protected ' . $type . ' $' . $column->name . ' = null;';
                        } else {
                            $data_columns[] = 'protected ' . $type . ' $' . $column->name . ' = null;';
                        }
                        $data_columns[] = '';
                        if ($is_set) {
                            if ($is_encrypted) {
                                $set = [];
                                $set[] = '/**';
                                $set[] = '* @throws Exception';
                                $set[] = '* @throws FileWriteException';
                                $set[] = '* @throws BadFormatException';
                                $set[] = '* @throws EnvironmentIsBrokenException';
                                $set[] = '* @throws WrongKeyOrModifiedCiphertextException';
                                $set[] = '*/';
                                $set[] = 'public function set' . str_replace('.', '', Controller::name($column->name)) . '(' . $type . ' $' . $column->name . '=null): void';
                                $set[] = '{';
                                $set[] = '    $object = $this->object();';
                                $set[] = '    if(!$object){';
                                $set[] = '        throw new Exception(\'Object not set...\');';
                                $set[] = '    }';
                                $set[] = '    $this->' . $column->name . ' = $' . $column->name . ';';
                                $set[] = '    $url = $object->config(\'project.dir.data\') . \'Defuse/Email.key\';';
                                $set[] = '    if(File::exist($url)){';
                                $set[] = '        $key = Core::key($url);';
                                $set[] = '        if(is_array($this->' . $column->name . ')){';
                                $set[] = '            foreach($this->' . $column->name . ' as $nr => $value){';
                                $set[] = '                $this->' . $column->name . '[$nr] = Crypto::encrypt($value, $key);';
                                $set[] = '            }';
                                $set[] = '        } else {';
                                $set[] = '            $this->' . $column->name . ' = Crypto::encrypt($this->' . $column->name . ', $key);';
                                $set[] = '        }';
                                $set[] = '        $this->is_encrypted_' . mb_strtolower($column->name) . ' = true;';
                                $set[] = '    } else {';
                                $set[] = '        throw new Exception(\'Key not found...\');';
                                $set[] = '    }';
                                $set[] = '}';
                            } else {
                                $set = [];
                                $set[] = 'public function set' . str_replace('.', '', Controller::name($column->name)) . '(' . $type . ' $' . $column->name . '=null): void';
                                $set[] = '{';
                                $set[] = '    $this->' . $column->name . ' = $' . $column->name . ';';
                                $set[] = '}';
                            }
                            $data_functions[] = $set;
                        }
                        if ($is_get) {
                            if ($is_encrypted) {
                                $get = [];
                                $get[] = '/**';
                                $get[] = '* @throws Exception';
                                $get[] = '*/';
                                $get[] = 'public function get' . str_replace('.', '', Controller::name($column->name)) . '(): ' . $return_type;
                                $get[] = '{';
                                $get[] = '    try {';
                                $get[] = '        $object = $this->object();';
                                $get[] = '        if(!$object){';
                                $get[] = '            throw new Exception(\'Object not set...\');';
                                $get[] = '        }';
                                $get[] = '        if(!$this->is_encrypted_' . mb_strtolower($column->name) . '){';
                                $get[] = '            return $this->' . $column->name . ';';
                                $get[] = '        }';
                                $get[] = '        $url = $object->config(\'project.dir.data\') . \'Defuse/Email.key\';';
                                $get[] = '        if(File::exist($url)){';
                                $get[] = '             $string = File::read($url);';
                                $get[] = '             $key = Key::loadFromAsciiSafeString($string);';
                                $get[] = '             if(is_array($this->' . $column->name . ')){';
                                $get[] = '                 foreach($this->' . $column->name . ' as $nr => $value){';
                                $get[] = '                     $this->' . $column->name . '[$nr] = Crypto::decrypt($value, $key);';
                                $get[] = '                 }';
                                $get[] = '             } else {';
                                $get[] = '                 $this->' . $column->name . ' = Crypto::decrypt($this->' . $column->name . ', $key);';
                                $get[] = '             }';
                                $get[] = '             $this->is_encrypted_' . mb_strtolower($column->name) . ' = false;';
                                $get[] = '        } else {';
                                $get[] = '            throw new Exception(' . "'Key not found...'" . ');';
                                $get[] = '        }';
                                $get[] = '        return $this->' . $column->name . ';';
                                $get[] = '    } catch (Exception | BadFormatException | EnvironmentIsBrokenException | WrongKeyOrModifiedCiphertextException $exception) {';
                                $get[] = '        return null;';
                                $get[] = '    }';
                                $get[] = '}';
                            } else {
                                $get = [];
                                if(
                                    property_exists($column, 'options') &&
                                    property_exists($column->options, 'target') &&
                                    property_exists($column->options->target, 'node')
                                ){
                                    $use_node = true;
                                    $node_data = [];
                                    if(property_exists($column->options->target->node, 'class')){
                                        $node_data[] ='class: "' . $column->options->target->node->class . '"';
                                    }
                                    if(property_exists($column->options->target->node, 'class')){
                                        $node_data[] ='sort: ' . Build::node_sort($column->options->target->node->sort);
                                    }
                                    if(property_exists($column->options->target->node, 'relation')){
                                        if(empty($column->options->target->node->relation)){
                                            $node_data[]  = 'relation: false';
                                        } else {
                                            $node_data[]  = 'relation: true';
                                        }

                                    }
                                    if(property_exists($column->options->target->node, 'multiple')){
                                        if(empty($column->options->target->node->multiple)){
                                            $node_data[]  = 'multiple: false';
                                        } else {
                                            $node_data[]  = 'multiple: true';
                                        }

                                    }
                                    //use Raxon\Doctrine\Attribute\Node;
                                    /**
                                    #[Node(
                                    class: "Account.Role",
                                    sort: ["rank" => "ASC"],
                                    relation: true,
                                    multiple: true
                                    )]
                                     */
                                    $get[] = '#[Node(';
                                    $get[] = '    ' . implode(', ' . PHP_EOL . '        ', $node_data);
                                    $get[] = ')]';
                                }
                                $get[] = 'public function get' . str_replace('.', '', Controller::name($column->name)) . '(): ' . $return_type;
                                $get[] = '{';
                                $get[] = '    return $this->' . $column->name . ';';
                                $get[] = '}';
                            }
                            $data_functions[] = $get;
                        }
                    } else {
                        if (
                            property_exists($column, 'options') &&
                            property_exists($column->options, 'default') &&
                            $column->options->default !== null
                        ) {
                            if ($options_default_value === 'CURRENT_TIMESTAMP') {
                                $data_columns[] = 'protected ' . $type . ' $' . $column->name . ';';
                            } else {
                                if ($options_default_value !== null) {
                                    if (is_numeric($options_default_value)) {
                                        $options_default_value = $options_default_value + 0;
                                        $data_columns[] = 'protected ' . $type . ' $' . $column->name . ' = ' . $options_default_value . ';';
                                    } else {
                                        if ($options_default_value === false) {
                                            $data_columns[] = 'protected ' . $type . ' $' . $column->name . ' = false;';
                                        } elseif ($options_default_value === true) {
                                            $data_columns[] = 'protected ' . $type . ' $' . $column->name . ' = true;';
                                        } else {
                                            $data_columns[] = 'protected ' . $type . ' $' . $column->name . ' = "' . $options_default_value . '";';
                                        }
                                    }
                                }
//                                    $data_columns[] = 'protected ' . $type . ' $' . $column->name . ' = "' . $column->options->default . '";';
                            }
                        } else {
                            $data_columns[] = 'protected ' . $type . ' $' . $column->name . ';';
                        }
                        if ($is_set) {
                            if ($is_encrypted) {
                                $set = [];
                                $set[] = '/**';
                                $set[] = '* @throws Exception';
                                $set[] = '* @throws FileWriteException';
                                $set[] = '* @throws BadFormatException';
                                $set[] = '* @throws EnvironmentIsBrokenException';
                                $set[] = '* @throws WrongKeyOrModifiedCiphertextException';
                                $set[] = '*/';
                                $set[] = 'public function set' . str_replace('.', '', Controller::name($column->name)) . '(' . $type . ' $' . $column->name . '): void';
                                $set[] = '{';
                                $set[] = '    $object = $this->object();';
                                $set[] = '    if(!$object){';
                                $set[] = '        throw new Exception(\'Object not set...\');';
                                $set[] = '    }';
                                $set[] = '    $this->' . $column->name . ' = $' . $column->name . ';';
                                $set[] = '    $url = $object->config(\'project.dir.data\') . \'Defuse/Email.key\';';
                                $set[] = '    if(File::exist($url)){';
                                $set[] = '        $key = Core::key($url);';
                                $set[] = '        if(is_array($this->' . $column->name . ')){';
                                $set[] = '            foreach($this->' . $column->name . ' as $nr => $value){';
                                $set[] = '                $this->' . $column->name . '[$nr] = Crypto::encrypt($value, $key);';
                                $set[] = '            }';
                                $set[] = '        } else {';
                                $set[] = '            $this->' . $column->name . ' = Crypto::encrypt($this->' . $column->name . ', $key);';
                                $set[] = '        }';
                                $set[] = '        $this->is_encrypted_' . mb_strtolower($column->name) . ' = true;';
                                $set[] = '    } else {';
                                $set[] = '        throw new Exception(\'Key not found...\');';
                                $set[] = '    }';
                                $set[] = '}';
                            } else {
                                $set = [];
                                $set[] = 'public function set' . str_replace('.', '', Controller::name($column->name)) . '(' . $type . ' $' . $column->name . '): void';
                                $set[] = '{';
                                $set[] = '    $this->' . $column->name . ' = $' . $column->name . ';';
                                $set[] = '}';
                            }
                            $data_functions[] = $set;
                        }
                        if ($is_get) {
                            if ($is_encrypted) {
                                $get = [];
                                $get[] = '/**';
                                $get[] = '* @throws Exception';
                                $get[] = '*/';
                                $get[] = 'public function get' . str_replace('.', '', Controller::name($column->name)) . '(): ' . $return_type;
                                $get[] = '{';
                                $get[] = '    try {';
                                $get[] = '        $object = $this->object();';
                                $get[] = '        if(!$object){';
                                $get[] = '            throw new Exception(\'Object not set...\');';
                                $get[] = '        }';
                                $get[] = '        if(!$this->is_encrypted_' . mb_strtolower($column->name) . '){';
                                $get[] = '            return $this->' . $column->name . ';';
                                $get[] = '        }';
                                $get[] = '        $url = $object->config(\'project.dir.data\') . \'Defuse/Email.key\';';
                                $get[] = '        if(File::exist($url)){';
                                $get[] = '             $string = File::read($url);';
                                $get[] = '             $key = Key::loadFromAsciiSafeString($string);';
                                $get[] = '             if(is_array($this->' . $column->name . ')){';
                                $get[] = '                 foreach($this->' . $column->name . ' as $nr => $value){';
                                $get[] = '                     $this->' . $column->name . '[$nr] = Crypto::decrypt($value, $key);';
                                $get[] = '                 }';
                                $get[] = '             } else {';
                                $get[] = '                 $this->' . $column->name . ' = Crypto::decrypt($this->' . $column->name . ', $key);';
                                $get[] = '             }';
                                $get[] = '             $this->is_encrypted_' . mb_strtolower($column->name) . ' = false;';
                                $get[] = '        } else {';
                                $get[] = '            throw new Exception(' . "'Key not found...'" . ');';
                                $get[] = '        }';
                                $get[] = '        return $this->' . $column->name . ';';
                                $get[] = '    } catch (Exception | BadFormatException | EnvironmentIsBrokenException | WrongKeyOrModifiedCiphertextException $exception) {';
                                $get[] = '        return $this->' . $column->name . ';';
                                $get[] = '    }';
                                $get[] = '}';
                            } else {
                                $get = [];
                                if(
                                    property_exists($column, 'options') &&
                                    property_exists($column->options, 'target') &&
                                    property_exists($column->options->target, 'node')
                                ){
                                    $use_node = true;
                                    $node_data = [];
                                    if(property_exists($column->options->target->node, 'class')){
                                        $node_data[] ='class: "' . $column->options->target->node->class . '"';
                                    }
                                    if(property_exists($column->options->target->node, 'class')){
                                        $node_data[] ='sort: ' . Build::node_sort($column->options->target->node->sort);
                                    }
                                    if(property_exists($column->options->target->node, 'relation')){
                                        if(empty($column->options->target->node->relation)){
                                            $node_data[]  = 'relation: false';
                                        } else {
                                            $node_data[]  = 'relation: true';
                                        }

                                    }
                                    if(property_exists($column->options->target->node, 'multiple')){
                                        if(empty($column->options->target->node->multiple)){
                                            $node_data[]  = 'multiple: false';
                                        } else {
                                            $node_data[]  = 'multiple: true';
                                        }

                                    }
                                    //use Raxon\Doctrine\Attribute\Node;
                                    /**
                                     #[Node(
                                        class: "Account.Role",
                                        sort: ["rank" => "ASC"],
                                        relation: true,
                                        multiple: true
                                    )]
                                     */
                                    $get[] = '#[Node(';
                                    $get[] = '    ' . implode(', ' . PHP_EOL . '        ', $node_data);
                                    $get[] = ')]';
                                }

                                $get[] = 'public function get' . str_replace('.', '', Controller::name($column->name)) . '(): ' . $return_type;
                                $get[] = '{';
                                $get[] = '    return $this->' . $column->name . ';';
                                $get[] = '}';
                            }
                            $data_functions[] = $get;
                        }
                    }
                }
            }
            $use = [];
            $use[] = 'DateTime';
            $use[] = '';
            if (array_key_exists(0, $encrypted)) {
                $use[] = 'Defuse\Crypto\Crypto';
                $use[] = 'Defuse\Crypto\Exception\BadFormatException';
                $use[] = 'Defuse\Crypto\Exception\EnvironmentIsBrokenException';
                $use[] = 'Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException';
                $use[] = 'Defuse\Crypto\Key';
                $use[] = '';
                $use[] = 'Raxon\App';
                $data_columns[] = 'protected App $object;';
                $record_object = [];
                $record_object[] = 'public function object($object=null): ?App';
                $record_object[] = '{';
                $record_object[] = '    if($object !== null){';
                $record_object[] = '        $this->setObject($object);';
                $record_object[] = '    }';
                $record_object[] = '    return $this->getObject();';
                $record_object[] = '}';
                $record_object_set = [];
                $record_object_set[] = 'public function setObject(App $object): void';
                $record_object_set[] = '{';
                $record_object_set[] = '    $this->object = $object;';
                $record_object_set[] = '}';
                $record_object_get = [];
                $record_object_get[] = 'public function getObject(): ?App';
                $record_object_get[] = '{';
                $record_object_get[] = '    return $this->object;';
                $record_object_get[] = '}';
                $data_functions[] = $record_object;
                $data_functions[] = $record_object_set;
                $data_functions[] = $record_object_get;
                foreach ($encrypted as $nr => $column) {
                    $data_columns[] = 'protected bool $is_encrypted_' . mb_strtolower($column) . ' = true;';
                }
            }
            $use[] = '';
            $use[] = 'Doctrine\ORM\Mapping as ORM';
            $use[] = 'Doctrine\ORM\Mapping\PrePersist';
            $use[] = 'Doctrine\ORM\Mapping\PreUpdate';
            $use[] = 'Doctrine\ORM\Event\PrePersistEventArgs';
            $use[] = 'Doctrine\ORM\Event\PreUpdateEventArgs';
            if($use_node === true){
                $use[] = 'Raxon\Doctrine\Attribute\Node';
            }
            $use[] = '';
            $use[] = 'Raxon\Module\Core';
            $use[] = 'Raxon\Module\File';
            $use[] = '';
            $use[] = 'Exception';
            $use[] = '';
            $use[] = 'Raxon\Exception\FileWriteException';
            foreach ($use as $usage) {
                if ($usage === '') {
                    $data[] = '';
                } else {
                    $data[] = 'use ' . $usage . ';';
                }
            }
            $repositoryClass = 'Repository\\' . $entity . 'Repository';
            $data[] = '';
            $data[] = '#[ORM\Entity(' .
                PHP_EOL .
                '    repositoryClass: "' .
                $repositoryClass .
                '"' .
                PHP_EOL .
                ')]';
            $data[] = '#[ORM\Table(name: "' . $table . '")]';
            if (
                $is_uuid ||
                (
                    $is_created && $type_is_created
                ) ||
                (
                    $is_updated && $type_is_updated
                ) ||
                array_key_exists(0, $encrypted) ||
                $is_updated
            ) {
                $data[] = '#[ORM\HasLifecycleCallbacks]';
            }
            $data[] = 'class ' . $entity . ' {';
            $data[] = '';
            foreach ($data_columns as $row) {
                $data[] = '    ' . $row;
            }
            $data[] = '';
            $data[] = '    public function __construct()';
            $data[] = '    {';
            foreach ($construct as $row) {
                $data[] = '        ' . $row;
            }
            $data[] = '    }';
            $data[] = '';
            foreach ($data_functions as $set) {
                foreach ($set as $row) {
                    $data[] = '    ' . $row;
                }
                $data[] = '';
            }
            if (
                $is_uuid ||
                (
                    $is_created && $type_is_created
                ) ||
                (
                    $is_updated && $type_is_updated
                )
            ) {
                $data[] = '    #[PrePersist]';
                $data[] = '    public function prePersist(PrePersistEventArgs $args): void';
                $data[] = '    {';
                if ($is_uuid) {
                    $data[] = '        $this->setUuid(Core::uuid());';
                }
                if ($is_created && $type_is_created) {
                    $data[] = '        $this->setIsCreated(new ' . $type_is_created . '());';
                }
                if ($is_updated && $type_is_updated) {
                    $data[] = '        $this->setIsUpdated(new ' . $type_is_updated . '());';
                }
                $data[] = '    }';
                $data[] = '';
            }
            if (
                array_key_exists(0, $encrypted) ||
                $is_updated
            ) {
                if (array_key_exists(0, $encrypted)) {
                    $data[] = '    /**';
                    $data[] = '    * @throws WrongKeyOrModifiedCiphertextException';
                    $data[] = '    * @throws BadFormatException';
                    $data[] = '    * @throws FileWriteException';
                    $data[] = '    * @throws EnvironmentIsBrokenException';
                    $data[] = '    * @throws Exception';
                    $data[] = '    */';
                }
                $data[] = '    #[PreUpdate]';
                $data[] = '    public function preUpdate(PreUpdateEventArgs $args): void';
                $data[] = '    {';
                if (array_key_exists(0, $encrypted)) {
                    $data[] = '        $object = $this->getObject();';
                    $data[] = '        if($object){';
                    foreach ($encrypted as $nr => $column) {
                        $data[] = '            if($this->is_encrypted_' . mb_strtolower($column) . ' === false){';
                        $data[] = '                $this->set' . str_replace('.', '', Controller::name($column)) . '($this->get' . str_replace('.', '', Controller::name($column)) . '());';
                        $data[] = '            }';
                    }
                    $data[] = '        }';
                }
                if ($is_updated) {
                    $data[] = '        $this->setIsUpdated(new ' . $type_is_updated . '());';
                }
                $data[] = '    }';
            }
            $data[] = '}';
            Dir::create($target_dir, Dir::CHMOD);
            File::write($target, implode(PHP_EOL, $data));
            File::permission($object, [
                'dir' => $target_dir,
                'file' => $target
            ]);
            echo 'Write: ' . $target . PHP_EOL;
        }
    }

    /**
     * @throws FileWriteException
     * @throws Exception
     */
    public static function repository(App $object, string $class, object $role, array | object $node, array $options=[]): void
    {
        if(is_object($node)){
            $node_class = get_class($node);
            switch($node_class){
                case 'stdClass':
                    $node = new Data($node);
                    break;
                default:
                    throw new Exception('unknown node class: ' . $node_class);
            }
        }
        elseif(is_array($node)){
            $node = new Data($node);
        } else {
            return;
        }
        if($node->has('table')) {
            $table = $node->get('table');
            $entity = $node->get('entity');
            $repository = $entity .  'Repository';
            $target_dir = $object->config('project.dir.source') .
                'Repository' .
                $object->config('ds')
            ;
            $target = $target_dir .
                $repository .
                $object->config('extension.php')
            ;
            if(!File::exist($target)){
                $data = [];
                $data[] = '<?php';
                $data[] = '';
                $data[] = 'namespace Repository;';
                $data[] = '';
                $data[] = 'use Doctrine\ORM\EntityRepository;';
                $data[] = '';
                $data[] = 'class ' . $repository . ' extends EntityRepository';
                $data[] = '{';
                $data[] = '';
                $data[] = '}';
                Dir::create($target_dir, Dir::CHMOD);
                File::write($target, implode(PHP_EOL, $data));
                File::permission($object, [
                    'dir' => $target_dir,
                    'file' => $target
                ]);
                echo 'Write: ' . $target . PHP_EOL;
            }
        }
    }

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public static function sql(App $object, string $class, object $role, object|null $connection=null, object|null $node=null, array $options=[]): void
    {
        if(is_object($node)){
            $node_class = get_class($node);
            switch($node_class) {
                case 'stdClass':
                    $node = new Data($node);
                    break;
                default:
                    throw new Exception('unknown class: ' . $node_class);
            }
        }
        elseif(is_array($node)){
            $node = new Data($node);
        }
        $em = $connection->manager ?? null;
        if(!$em){
            throw new Exception('em (Entity manager) not set...');
        }
        $platform = $em->getConnection()->getDatabasePlatform();
        $schema = new \Doctrine\DBAL\Schema\Schema();
        $schema_table = $schema->createTable($node->get('table'));
        $columns = $node->get('column');
        foreach($columns as $nr => $column){
            if(property_exists($column, 'type')){
                if(property_exists($column, 'options')){
                    $schema_options = (array) $column->options;
                    if(array_key_exists('nullable', $schema_options)){
                        $schema_options['notnull'] = !$schema_options['nullable'];
                        unset($schema_options['nullable']);
                    }
                    if(array_key_exists('id', $schema_options)){
                        unset($schema_options['id']);
                    }
                    if(array_key_exists('definition', $schema_options)){
                        $schema_options['columnDefinition'] = $schema_options['definition'];
                        unset($schema_options['definition']);
                    }
                    switch($column->type){
                        case 'one-to-one':
                            if(
                                property_exists($column->options, 'join') &&
                                property_exists($column->options->join, 'column')
                            ){
                                $schema_options_join = [];
                                if(property_exists($column->options->join->column[0], 'nullable')){
                                    $schema_options_join['notnull'] = !$column->options->join->column[0]->nullable;
                                }
                                if(
                                    property_exists($column->options->join->column[0], 'name') &&
                                    property_exists($column->options->join->column[0], 'type')
                                ){
                                    if(empty($schema_options_join)){
                                        $schema_table->addColumn($column->options->join->column[0]->name, $column->options->join->column[0]->type);
                                    } else {
                                        $schema_table->addColumn($column->options->join->column[0]->name, $column->options->join->column[0]->type, $schema_options_join);
                                    }
                                    //create column (bigint user_id for example)
                                }
                            }
                            break;
                        case 'one-to-many':
                            if(
                                property_exists($column->options, 'join') &&
                                property_exists($column->options->join, 'column')
                            ){
                                //create column (bigint user_id for example)
                            }
                            break;
                        case 'many-to-many':
                            if(
                                property_exists($column->options, 'join') &&
                                property_exists($column->options->join, 'column') &&
                                property_exists($column->options->join, 'table')
                            ){
                                //create column (bigint user_id for example)

                                /*
                                $myForeign = $schema->createTable("my_foreign");
                                $myForeign->addColumn("id", "integer");
                                $myForeign->addColumn("user_id", "integer");
                                $myForeign->addForeignKeyConstraint($myTable, ["user_id"], ["id"], ["onUpdate" => "CASCADE"]);
                                */

                                $schema_table_foreign = $schema->createTable($column->options->join->table);
                                foreach($column->options->join->column as $foreign_column) {
                                    $schema_table_foreign->addColumn($foreign_column->name, $foreign_column->type);
                                }
                                $schema_table_foreign->addForeignKeyConstraint($node->get('table'), [ $column->options->join->column[0]->name ], [ $column->options->join->column[1]->name ]);
                            }
                            break;
                        default:
                            if(empty($schema_options)){
                                $schema_table->addColumn($column->name, $column->type);
                            } else {
                                if(array_key_exists('target', $schema_options)){
                                    unset($schema_options['target']);
                                }
                                $schema_table->addColumn($column->name, $column->type, $schema_options);
                            }
                            break;
                    }
                } else {
                    $schema_table->addColumn($column->name, $column->type);
                }
            }
        }
        if($node->has('primary_key')){
            $schema_table->setPrimaryKey($node->get('primary_key'));
        }
        /*
        if($node->has('unique')){
            foreach($node->get('unique') as $index){
                if(is_array($index)){
                    $schema_table->addUniqueIndex($index);
                } else {
                    $schema_table->addUniqueIndex([$index]);
                }
            }
        }
        */
        if($node->has('index')){
            foreach($node->get('index') as $index){
                if(is_array($index)){
                    $schema_table->addIndex($index);
                } else {
                    $schema_table->addIndex([$index]);
                }
            }
        }
        if($node->has('unique')){
            foreach($node->get('unique') as $index){
                if(is_array($index)){
                    $schema_table->addUniqueIndex($index);
                } else {
                    $schema_table->addUniqueIndex([$index]);
                }
            }
        }
        $sql_drop = $schema->toDropSql($platform);
        $sql_to = $schema->toSql($platform);

//        $model = new Node($object);
//        $record = (object) [];
//        $record->uuid = $node->get('uuid');
//        $record->sql = (object) [
//            'drop' => $sql_drop,
//            'to' => $sql_to
//        ];
//        $patch = $model->patch($node->get('#class'), $model->role_system(), $record);
//        d($patch);
        $node->set('sql', (object) [
            'drop' => $sql_drop,
            'to' => $sql_to
        ]);
        if($sql_to){
            foreach($sql_to as $line){
                //add to log
                echo $line . ';' . PHP_EOL;
            }
            $em_connection = $em->getConnection();
            if($em_connection){
                foreach($sql_to as $line){
                    $stmt = $em_connection->prepare($line);
                    $result = $stmt->executeQuery();
                }
                if(property_exists($connection, 'path')){
                    File::permission($object, [
                        'dir' => $connection->path
                    ]);
                }
            }
        }
    }

    private static function node_sort($sort): string
    {
        $result = '[';
        foreach($sort as $key => $value){
            if(is_string($value)){
                $result .= '"' .$key .'" => "' . $value . '"';
            } else {
                $result .= '"' .$key .'" => ' . $value;
            }
        }
        $result .= ']';
        return $result;
    }

}