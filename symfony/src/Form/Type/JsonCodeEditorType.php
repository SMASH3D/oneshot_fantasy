<?php

declare(strict_types=1);

namespace App\Form\Type;

use EasyCorp\Bundle\EasyAdminBundle\Form\Type\CodeEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Custom Symfony Form Type utilizing a generic JSON string transformer for CodeEditor integration.
 */
class JsonCodeEditorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            function (?array $arrayValue): string {
                if (null === $arrayValue) {
                    return '{}';
                }

                return json_encode($arrayValue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
            },
            function (?string $jsonString): array {
                if (null === $jsonString || '' === trim($jsonString)) {
                    return [];
                }

                $decoded = json_decode($jsonString, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new TransformationFailedException('Invalid JSON format provided.');
                }

                return is_array($decoded) ? $decoded : [];
            }
        ));
    }

    public function getParent(): string
    {
        return CodeEditorType::class;
    }
}
