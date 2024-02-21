<?php

namespace Bx\Model\Fetcher\Ext\Model;

use Bx\Model\AbsOptimizedModel;

class PropertyModel extends AbsOptimizedModel
{
    public const FILE_TYPE = 'file';
    public const LINKED_ELEMENT_TYPE = 'element';
    public const USER_TYPE = 'user';
    public const ENUM_TYPE = 'enum';
    public const SECTION_TYPE = 'section';
    public const HL_TYPE = 'hl';

    protected function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'code' => $this->getCode(),
            'name' => $this->getName(),
            'type' => $this->getType(),
            'isMultiple' => $this->isMultiple(),
        ];
    }

    public function getType(): string
    {
        if ($this->getPropertyType() === 'F') {
            return static::FILE_TYPE;
        }
        if ($this->getPropertyType() === 'E') {
            return static::LINKED_ELEMENT_TYPE;
        }
        if (!in_array($this->getUserType(), ['UserID', 'employee'])) {
            return static::USER_TYPE;
        }
        if ($this->getPropertyType() === 'L') {
            return static::ENUM_TYPE;
        }
        if ($this->getPropertyType() === 'G') {
            return static::SECTION_TYPE;
        }
        if ($this->getUserType() === 'directory') {
            return static::HL_TYPE;
        }

        return '';
    }

    public function getId(): int
    {
        return (int) $this['ID'];
    }

    public function getCode(): string
    {
        return (string) $this['CODE'];
    }

    public function getName(): string
    {
        return (string) $this['NAME'];
    }

    public function getPropertyType(): string
    {
        return (string) $this['PROPERTY_TYPE'];
    }

    public function getIblockLinkId(): int
    {
        return (int) $this['LINK_IBLOCK_ID'];
    }

    public function isMultiple(): bool
    {
        return ($this['MULTIPLE'] ?? 'N') === 'Y';
    }

    public function getUserType(): string
    {
        return (string) $this['USER_TYPE'];
    }

    public function getHlTableName(): string
    {
        $typeSettings = $this->getUserTypeSettings();
        return (string) ($typeSettings['TABLE_NAME'] ?? '');
    }

    public function getUserTypeSettings(): array
    {
        $typeSettings = unserialize($this->getUserTypeSettingsStr());
        if ($typeSettings === false) {
            return [];
        }

        return is_array($typeSettings) ? $typeSettings : [];
    }

    public function getUserTypeSettingsStr(): string
    {
        return (string) $this['USER_TYPE_SETTINGS'];
    }
}
