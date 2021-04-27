<?php

namespace MeiliSearch\Bundle\Test\Normalizer;

use MeiliSearch\Bundle\Test\Entity\Comment;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Class CommentNormalizer.
 *
 * @package MeiliSearch\Bundle\Test\Normalizer
 */
class CommentNormalizer implements NormalizerInterface
{
    public function normalize($object, $format = null, array $context = []): array
    {
        return [
            'content' => $object->getContent(),
            'post_title' => $object->getPost()->getTitle(),
        ];
    }

    public function supportsNormalization($data, $format = null): bool
    {
        return $data instanceof Comment;
    }
}
