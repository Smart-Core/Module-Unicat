<?php

namespace SmartCore\Module\Unicat\Generator;

use Sensio\Bundle\GeneratorBundle\Generator\Generator;
use SmartCore\Module\Unicat\Entity\UnicatConfiguration;

class DoctrineEntityGenerator extends Generator
{
    /**
     * @param string $dir
     * @param string $namespace
     * @param UnicatConfiguration $configuration
     * @param string $table_prefix
     */
    public function generate($dir, $namespace, UnicatConfiguration $configuration, $table_prefix = 'unicat_')
    {
        $parameters = [
            'configuration' => $configuration,
            'namespace'     => $namespace,
            'table_prefix'  => $table_prefix,
        ];

        $entities = [
            'Taxon',
            'Item',
        ];

        foreach ($entities as $entity) {
            $this->renderFile('entity/'.$entity.'.php.twig', $dir.'/'.$entity.'.php', $parameters);
        }

        if (!empty($configuration->getAttributes())) {
            foreach ($configuration->getAttributes() as $attribute) {
                if ($attribute->getIsDedicatedTable()) {
                    switch ($attribute->getType()) {
                        case 'checkbox':
                            $type = 'Bool';
                            break;
                        case 'integer':
                        case 'choice':
                            $type = 'Int';
                            break;
                        case 'smallint':
                            $type = 'Smallint';
                            break;
                        case 'string':
                        case 'text':
                            $type = 'String';
                            break;
                        case 'textarea':
                            $type = 'Text';
                            break;
                        default:
                            throw new \Exception('Unsupported value type: '.$attribute->getType());
                    }

                    $parameters['name'] = $attribute->getName();
                    $parameters['name_camel_case'] = $this->camelCase($attribute->getName());
                    $parameters['type'] = $type;

                    $template = 'Value'; // @todo uniquie

                    $this->renderFile('entity/'.$template.'.php.twig', $dir.'/Value'.$parameters['name_camel_case'].'.php', $parameters);
                }
            }
        }
    }

    /**
     * @param string $name
     *
     * @return string
     */
    public function camelCase($name)
    {
        $str = '';
        foreach (explode('_', $name) as $val) {
            if (!empty($val)) {
                $str .= ucfirst($val);
            }
        }

        return $str;
    }
}
