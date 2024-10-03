<?php

namespace App\Form;

use App\Entity\Contact;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        ->add('Nom', TextType::class, [
            'attr' => [
                'placeholder' => 'Your Name',
                'class' => 'form-control',
            ],
        ])
        ->add('Email', EmailType::class, [
            'attr' => [
                'placeholder' => 'Your Email',
                'class' => 'form-control',
            ],
        ])
        ->add('Sujet', ChoiceType::class, [
            'choices' => [
                'Writing Article' => 'Writing Article',
                'Become Author' => 'Become Author',
                'Guest Posting' => 'Guest Posting',
                'Personal Question' => 'Personal Question',
            ],
            'attr' => [
                'class' => 'form-control',
            ],
            'placeholder' => 'Choose a Subject', // Set as placeholder
            'required' => false, // This makes sure the placeholder is not a valid option
            'empty_data' => null, // No selection by default
        ])
        ->add('Message', TextareaType::class, [
            'attr' => [
                'placeholder' => 'Your Message',
                'class' => 'form-control',
                'cols' => 30,  // Optional, if you want to specify directly
                'rows' => 3,   // Optional, if you want to specify directly
            ],
        ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Contact::class,
        ]);
    }
}
