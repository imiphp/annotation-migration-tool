<?php
declare(strict_types=1);

namespace Imiphp\Tool\AnnotationMigration;

use Yurun\Doctrine\Common\Annotations\Reader;

class HandleCode
{
    protected bool $modified = false;
    private ?string $rewriteCode = null;

    public function __construct(
        readonly public string $filename,
        readonly public Reader $reader,
    ) {
    }

    public function isModified(): bool
    {
        return $this->modified;
    }

    public function setModified(): void
    {
        $this->modified = true;
    }

    public function getContents(): string
    {
        return file_get_contents($this->filename);
    }

    public function setRewriteCode(string $code): void
    {
        $this->rewriteCode = $code;
    }

    public function getRewriteCode(): ?string
    {
        return $this->rewriteCode;
    }
}
