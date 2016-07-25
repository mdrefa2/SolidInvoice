<?php

/*
 * This file is part of CSBill project.
 *
 * (c) 2013-2016 Pierre du Plessis <info@customscripts.co.za>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace CSBill\InstallBundle\Form\Type;

use CSBill\CoreBundle\Form\Type\Select2;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

class DatabaseConfigType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $drivers = $options['drivers'];

        $builder->add(
            'driver',
            Select2::class,
            [
                'help' => 'Only MySQL is supported at the moment',
                'choices' => array_flip($drivers),
                'choices_as_values' => true,
                'placeholder' => 'Select Database Driver',
                'constraints' => new Constraints\NotBlank(),
            ]
        );

        $builder->add(
            'host',
            null,
            [
                'constraints' => new Constraints\NotBlank(),
            ]
        );

        $builder->add(
            'port',
            IntegerType::class,
            [
                'constraints' => new Constraints\Type(['type' => 'integer']),
                'required' => false,
            ]
        );

        $builder->add(
            'user',
            null,
            [
                'constraints' => new Constraints\NotBlank(),
            ]
        );

        $builder->add(
            'password',
            PasswordType::class,
            [
                'required' => false,
            ]
        );

        $builder->add(
            'name',
            null,
            [
                'label' => 'Database Name',
                'constraints' => new Constraints\NotBlank(),
                'required' => false,
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired(['drivers']);
    }

    /**
     * @return string
     */
    public function getBlockPrefix()
    {
        return 'database_config';
    }
}
