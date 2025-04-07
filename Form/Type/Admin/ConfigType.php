<?php
namespace Plugin\UnivaPay\Form\Type\Admin;

use Plugin\UnivaPay\Entity\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Uuid;

class ConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('app_id', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('app_secret', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('widget_url', UrlType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('api_url', UrlType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('capture', CheckboxType::class, [
                'required' => false,
                'label'    => false
            ])
            ->add('webhook_auth', TextType::class, [
                'required' => false,
                'constraints' => [
                    new Uuid(),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Config::class,
        ]);
    }
}
