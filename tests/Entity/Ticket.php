<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
#[ORM\Entity]
class Ticket
{
    /**
     * @ORM\Id
     *
     * @ORM\Column(type="integer")
     */
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    /**
     * @ORM\Column(type="string")
     */
    #[ORM\Column(type: Types::STRING)]
    private string $barcode;

    /**
     * @ORM\Column(type="boolean", options={"default"=false})
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $sold;

    /**
     * @param int    $id
     * @param string $barcode
     * @param bool   $sold
     */
    public function __construct(int $id, string $barcode, bool $sold)
    {
        $this->id = $id;
        $this->barcode = $barcode;
        $this->sold = $sold;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): Ticket
    {
        $this->id = $id;

        return $this;
    }

    public function getBarcode(): string
    {
        return $this->barcode;
    }

    public function setBarcode(string $barcode): Ticket
    {
        $this->barcode = $barcode;

        return $this;
    }

    public function isSold(): bool
    {
        return $this->sold;
    }

    public function setSold(bool $sold): Ticket
    {
        $this->sold = $sold;

        return $this;
    }
}
