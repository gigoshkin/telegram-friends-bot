<?php

namespace App\Form;

use App\Entity\ChatExportFile;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\UX\Dropzone\Form\DropzoneType;

class ChatExportUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', DropzoneType::class, [
                'label' => 'Chat Export File (JSON)',
                'attr' => [
                    'placeholder' => 'Drag and drop your file here or click to browse',
                ],
                'required' => true,
                'mapped' => false,
                'constraints' => [
                    new NotNull(message: 'Please select a file to upload'),
                    new File(maxSize: '100M', mimeTypes: ['application/json', 'text/plain'], mimeTypesMessage: 'Please upload a valid JSON file'),
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Upload & Train Bot',
                'attr' => ['class' => 'btn btn-primary'],
            ]);
    }
}
