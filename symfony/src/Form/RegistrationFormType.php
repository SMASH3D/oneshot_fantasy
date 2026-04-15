<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Registration form mapping to the User entity.
 * The plain password is handled separately and never stored directly on the entity.
 */
class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email address',
                'attr'  => [
                    'placeholder'  => 'you@example.com',
                    'autocomplete' => 'email',
                ],
            ])
            ->add('nickname', TextType::class, [
                'label' => 'Nickname',
                'attr'  => [
                    'placeholder'  => 'YourNickname',
                    'autocomplete' => 'username',
                    'maxlength'    => 50,
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type'            => PasswordType::class,
                'mapped'          => false,
                'first_options'   => [
                    'label' => 'Password',
                    'attr'  => [
                        'placeholder'  => 'At least 8 characters',
                        'autocomplete' => 'new-password',
                    ],
                ],
                'second_options'  => [
                    'label' => 'Confirm password',
                    'attr'  => [
                        'placeholder'  => 'Repeat your password',
                        'autocomplete' => 'new-password',
                    ],
                ],
                'invalid_message' => 'The passwords do not match.',
                'constraints'     => [
                    new NotBlank(['message' => 'Please enter a password.']),
                    new Length([
                        'min'        => 8,
                        'minMessage' => 'Your password must be at least {{ limit }} characters.',
                        'max'        => 4096,
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
