<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class ShippingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class, ['constraints' => [new NotBlank()]])
            ->add('address', TextType::class, ['constraints' => [new NotBlank()]])
            ->add('city', TextType::class, ['constraints' => [new NotBlank()]])
            ->add('postalCode', TextType::class, ['constraints' => [new NotBlank()]])
            ->add('country', TextType::class, ['constraints' => [new NotBlank()]]);
    }

    public function configureOptions(\Symfony\Component\OptionsResolver\OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null, 'csrf_protection' => true]);
    }
}
