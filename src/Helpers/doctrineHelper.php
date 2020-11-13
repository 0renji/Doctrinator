<?php
namespace App\Helpers;

use Symfony\Component\Yaml\Yaml;

class doctrineHelper
{
    /**
     * @param array $entityMetaObject
     * @param array $propertyObject
     * @param string $doctrineTypesMapperFilepath
     * @return array
     */
    public function createEntityFileString (array $entityMetaObject, array $propertyObject, array $classMethods, string $doctrineTypesMapperFilepath) {
        $logMessages = [];
        $entityString = '';

        $typesYaml = Yaml::parse(file_get_contents($doctrineTypesMapperFilepath));

        !$entityMetaObject['table']
            ? $entityString .= $this->createEntityHeader($entityMetaObject['destinationDirectory'], $entityMetaObject['name'])
            : $entityString .= $this->createEntityHeader($entityMetaObject['destinationDirectory'], $entityMetaObject['name'], $entityMetaObject['table']);

        // check if the class name is inside the exceptions if not check if All is given -> use the All
        // if all is empty use given property value and comment on it inside foreach
        $typesToMap = [];

        if ($typesYaml && count($typesYaml) > 0) {
            if ($typesYaml['All']) {
                $typesToMap = $typesYaml['All'];
            }

            if ($typesYaml['Exceptions'] && in_array($entityMetaObject['name'], $typesYaml['Exceptions'])) {
                $typesToMap = $typesYaml['Exceptions'][$entityMetaObject['name']];
            }
        }

        if (count($propertyObject) > 0) {
            foreach ($propertyObject as $propertyKey => $propertyValue) {
                $generated = false;
                $nullable = false;

                $todoString = "\t". '// TODO change the given type to a doctrine usable type'. "\n";
                $logMessage = 'Property ' . $propertyKey . '\'s type is not listed inside the types mapper yaml, injecting it\'s given type and adding TODO.';

                if(count($typesToMap) == 0) {
                   $logMessages[] = $logMessage;
                    $entityString .= $todoString;
                } else if (array_key_exists($propertyValue, $typesToMap)){
                    if($typesToMap[$propertyValue] && $typesToMap[$propertyValue]['type']) {
                        if(array_key_exists('generator', $typesToMap[$propertyValue])) {
                            $generated = true;
                        }
                        if(array_key_exists('nullable', $typesToMap[$propertyValue])) {
                            $nullable = true;
                        }

                        $propertyValue = $typesToMap[$propertyValue]['type'];
                    } else {
                        $logMessages[] = $logMessage;
                        $entityString .= $todoString;
                    }
                }

                if(strpos($propertyValue, '_or_null')) {
                    $nullable = true;
                }

                // There are many generator strategies, could be implemented in future but for now AUTO is default
                $entityString .= $this->createProperty($propertyKey, $propertyValue, $generated, $nullable);
            }
        }

        if($classMethods && count($classMethods)) {
            foreach ($classMethods as $method) {
                $entityString .= "\n" . '// Method '. $method->name->name . ' has been removed, if it\'s needed please implement it by hand.' . "\n";
            }
        }

        $entityString .= "\n";

        return ['entityString' => $entityString . '}' ."\n", 'logMessages' => $logMessages];
    }

    /**
     * @param string $destinationFilepath
     * @param string $entityName
     * @param string|null $table
     * @return string
     */
    public function createEntityHeader (string $destinationFilepath, string $entityName, string $table = null) {
        $entityHeader = '<?php' . "\n";

        if (!$table) {
            $entityHeader .=
                '// ' . $destinationFilepath . "\n"
                . 'use Doctrine\ORM\Mapping as ORM;' . "\n"
                . '/**' . "\n"
                . '* @ORM\Entity' . "\n"
                . '*/' . "\n"
                . 'class ' . $entityName . ' {' . "\n";

            return $entityHeader;
        }

        $entityHeader .=
            '// ' . $destinationFilepath . "\n"
            . 'use Doctrine\ORM\Mapping as ORM;' . "\n"
            . '/**' . "\n"
            . '* @ORM\Entity' . "\n"
            . '* @ORM\Table(name="' . $table . '")' . "\n"
            . '*/' . "\n"
            . 'class ' . $entityName . ' { ' ."\n";

        return $entityHeader;
    }

    /**
     * @param string $propertyName
     * @param string $propertyType
     * @param bool $generated
     * @return string
     */
    private function createProperty (string $propertyName, string $propertyType, bool $generated, bool $nullable) {
        $propertyString = '';
        $annotation = $this->createPropertyAnnotation($propertyName, $propertyType, $generated, $nullable);
        $propertyString .= $annotation
            . "\t" . 'protected $' . $propertyName . ';' . "\n";

        return $propertyString ."\n";
    }

    /**
     * @param string $propertyName
     * @param string $propertyType
     * @param bool $generated
     * @return string
     */
    private function createPropertyAnnotation (string $propertyName, string $propertyType, bool $generated, bool $nullable) {
        $annotation = '';

        // check of the instance has a foreign key, indicated by name e.g. user_id or type id_or_null
        if(strpos($propertyName, '_id') && !strpos($propertyType, '_or_null')) {
            $nullable = false;
            $annotation .= "\t" . '// TODO create a n to n relation by hand for this property' . "\n";
        }

        $annotation .= "\t" . '/**' . "\n";

        switch ($propertyName) {
            case 'id':
                $annotation .=
                    "\t" .   ' * @ORM\Id' . "\n";
                if ($generated) {
                    $annotation .= "\t" . ' * @ORM\GeneratedValue' . "\n";
                }
        }

        switch ($propertyType) {
            case 'id':
                $annotation .= "\t" . ' * @ORM\Column(type="integer",';
            break;
            default:
                $annotation .= "\t" . ' * @ORM\Column(type="' . $propertyType .'",';
        }

        $annotation .= 'nullable='. var_export($nullable, true) . ')' . "\n";

        $annotation .=
            "\t" .   ' * @var ' . $propertyName . "\n"
            . "\t" . ' */';

        return $annotation . "\n";
    }
}
