<?php
namespace App\Helpers;

use PhpParser\PrettyPrinter;

class doctrineHelper
{
    /**
     * @param array $entityMetaObject
     * @param array $propertyObject
     * @param array $methodsAST
     * @return string
     */
    public function createEntityFileString (array $entityMetaObject, array $propertyObject, array $methodsAST) {
        $entityString = '';

        !$entityMetaObject['table']
            ? $entityString .= $this->createEntityHeader($entityMetaObject['destinationDirectory'], $entityMetaObject['name'])
            : $entityString .= $this->createEntityHeader($entityMetaObject['destinationDirectory'], $entityMetaObject['name'], $entityMetaObject['table']);


        if (count($propertyObject) > 0) {
            foreach ($propertyObject as $propertyKey => $propertyValue) {
                $entityString .= $this->createProperty($propertyKey, $propertyValue);
            }
        }

        $entityString .= "\n";

        $prettyPrinter = new PrettyPrinter\Standard;
        $methodStrings = explode("\n", $prettyPrinter->prettyPrint($methodsAST));
        foreach ($methodStrings as $methodString) {
            $entityString .= "\t" . $methodString . "\n";
        }

        return $entityString . '}' ."\n";
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
     * @return string
     */
    private function createProperty (string $propertyName, string $propertyType) {
        $propertyString = '';
        $annotation = $this->createPropertyAnnotation($propertyName, $propertyType);
        $propertyString .= $annotation
            . "\t" . 'protected $' . $propertyName . ';' . "\n";

        return $propertyString ."\n";
    }

    /**
     * @param string $propertyName
     * @param string $propertyType
     * @return string
     */
    private function createPropertyAnnotation (string $propertyName, string $propertyType) {
       // TODO maybe rely on a doctrineMapper.yaml
       $annotation = "\t" . '/**' . "\n";

        switch ($propertyName) {
            case 'id':
                $annotation .=
                    "\t" .   ' * @ORM\Id' . "\n"
                    . "\t" . ' * @ORM\GeneratedValue' . "\n";

        }

        switch ($propertyType) {
            case 'id':
                $annotation .= "\t" . ' * @ORM\Column(type="integer")' . "\n";
            break;
            default:
                $annotation .= "\t" . ' * @ORM\Column(type="' . $propertyType . '")' . "\n";
        }

        $annotation .=
            "\t" .   ' * @var ' . $propertyName . "\n"
            . "\t" . ' */';

        return $annotation . "\n";
    }
}
