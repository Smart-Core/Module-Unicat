<?php

namespace SmartCore\Module\Unicat\Form\Type;

use SmartCore\Module\Unicat\Entity\UnicatConfiguration;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AttributeFormType extends AbstractType
{
    /** @var UnicatConfiguration */
    protected $configuration;

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->configuration = $options['unicat_configuration'];

        $builder
            ->add('title', null, ['attr' => ['autofocus' => 'autofocus', 'placeholder' => 'Произвольная строка']])
            ->add('name',  null, ['attr' => ['placeholder' => 'Латинские буквы в нижем регистре и символы подчеркивания.']])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Text'        => 'text',
                    'Textarea'    => 'textarea',
                    'Integer'     => 'integer',
                    'Email'       => 'email',
                    'URL'         => 'url',
                    'Date'        => 'date',
                    'Datetime'    => 'datetime',
                    'Checkbox'    => 'checkbox',
                    'Image'       => 'image',
                    'Choice'      => 'choice',
                    'Multiselect' => 'multiselect',
                ],
            ])
            ->add('params_yaml',   null, ['attr' => ['data-editor' => 'yaml']])
            ->add('position')
            ->add('is_dedicated_table', null, ['required' => false])
            ->add('update_all_records_with_default_value', TextType::class, [
                'attr'     => ['placeholder' => 'Пустое поле - не обновлять записи'],
                'required' => false,
            ])
            ->add('is_enabled',    null, ['required' => false])
            ->add('is_link',       null, ['required' => false])
            ->add('is_required',   null, ['required' => false])
            ->add('is_show_title', null, ['required' => false])
            ->add('show_in_admin', null, ['required' => false])
            ->add('show_in_list',  null, ['required' => false])
            ->add('show_in_view',  null, ['required' => false])
            ->add('open_tag')
            ->add('close_tag')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => function (Options $options) {
                return $options['unicat_configuration']->getAttributeClass();
            },
            'unicat_configuration' => null,
        ]);
    }

    public function getBlockPrefix()
    {
        return 'unicat_attribute';
    }
}
