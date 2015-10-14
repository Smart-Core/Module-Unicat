<?php

namespace SmartCore\Module\Unicat\Form\Type;

use SmartCore\Bundle\CMSBundle\Module\AbstractNodePropertiesFormType;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\FormBuilderInterface;

class NodePropertiesFormType extends AbstractNodePropertiesFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $configurations = [];
        foreach ($this->em->getRepository('UnicatModule:UnicatConfiguration')->findAll() as $configuration) {
            $configurations[$configuration->getId()] = (string) $configuration;
        }

        $finder = new Finder();
        $finder->files()->sortByName()->depth('== 0')->name('*.html.twig')->in($this->kernel->getBundle('SiteBundle')->getPath().'/Resources/views/');

        $templates = ['' => ''];
        /** @var \Symfony\Component\Finder\SplFileInfo $file */
        foreach ($finder as $file) {
            $name = str_replace('.html.twig', '', $file->getFilename());
            $templates[$name] = $name;
        }

        $builder
            ->add('configuration_id', 'choice', [
                'choices'  => $configurations,
                'label'    => 'Configuration',
                'required' => false,
            ])
            ->add('use_item_id_as_slug', 'checkbox', [
                'label'    => 'Использовать ID записей в качестве URI',
                'required' => false,
            ])
            ->add('use_layout_for_items', 'choice', [
                'label'    => 'Применять общий шаблон для записей',
                'choices'  => $templates,
                'required' => false,
            ])
        ;
    }

    public function getName()
    {
        return 'smart_module_unicat_node_properties';
    }
}
