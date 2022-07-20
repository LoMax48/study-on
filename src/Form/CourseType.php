<?php

namespace App\Form;

use App\Entity\Course;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class CourseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'Буквенный код',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Заполните поле.'
                    ]),
                    new Length([
                        'max' => 255,
                    ])
                ]
            ])
            ->add('name', TextType::class, [
                'label' => 'Название',
                'constraints' => [
                    new NotBlank(),
                    new Length([
                        'max' => 255,
                    ])
                ]
            ])
            ->add('type', ChoiceType::class, [
                'data' => $options['type'],
                'mapped' => false,
                'choices' => [
                    'Аренда' => 'rent',
                    'Бесплатный' => 'free',
                    'Покупка' => 'buy',
                ],
            ])
            ->add('price', NumberType::class, [
                'attr' => [
                    'value' => $options['price'],
                ],
                'mapped' => false,
                'empty_data' => '',
                'required' => false,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Заполните поле.'
                    ])
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Описание',
                'required' => false,
                'constraints' => [
                    new Length([
                        'max' => 1000,
                        'maxMessage' => 'Описание превышает {{ limit }} символов.',
                    ])
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Course::class,
            'price' => 0.0,
            'type' => 'rent',
        ]);
        $resolver->setAllowedTypes('price', 'float');
        $resolver->setAllowedTypes('type', 'string');
    }
}
