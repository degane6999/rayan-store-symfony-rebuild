<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Category;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['constraints' => [new NotBlank()]])
            ->add('description', TextareaType::class, ['constraints' => [new NotBlank()]])
            ->add('price', NumberType::class, [
                'html5' => true,
                'scale' => 2,
                'constraints' => [new NotBlank(), new Positive()],
            ])
            ->add('stock', IntegerType::class, [
                'constraints' => [new NotBlank(), new PositiveOrZero()],
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
            ]);

        // Doctrine stores price as a DECIMAL string; the NumberType form field
        // works with floats. Convert between the two explicitly rather than
        // relying on implicit casts, which previously caused a MoneyType
        // configuration error in an earlier build attempt.
        $builder->get('price')->addModelTransformer(new CallbackTransformer(
            static fn (?string $decimal): ?float => null === $decimal ? null : (float) $decimal,
            static fn (?float $float): ?string => null === $float ? null : number_format($float, 2, '.', ''),
        ));

        // A negative stock value must surface as a form validation error
        // (HTTP 422-equivalent), not an uncaught exception from the entity
        // setter (which previously produced an unhandled 500).
        $builder->get('stock')->addModelTransformer(new CallbackTransformer(
            static fn (?int $stock): ?int => $stock,
            static function (?int $stock): ?int {
                if (null !== $stock && $stock < 0) {
                    throw new TransformationFailedException('Le stock ne peut pas être négatif.');
                }

                return $stock;
            },
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => \App\Entity\Product::class, 'allow_extra_fields' => true]);
    }
}
