<?php

namespace MeiliSearch\Bundle\Test\Normalizer;

use MeiliSearch\Bundle\Test\Entity\Comment;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Class CommentNormalizer
 *
 * @package MeiliSearch\Bundle\Test\Normalizer
 */
class CommentNormalizer implements NormalizerInterface
{

    /**
     * @inheritDoc
     */
    public function normalize($object, $format = null, array $context = [])
    {
        return [
            'content'    => $object->getContent(),
            'post_title' => $object->getPost()->getTitle(),
        ];
    }

    /**
     * @inheritDoc
     */
    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof Comment;
    }
}