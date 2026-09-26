<?php

namespace App\Api;

use App\Entity\Brand;
use App\Entity\Rating;
use App\Entity\Strain;
use App\Entity\StrainType;
use App\Entity\Terpene;
use App\Entity\User;

/**
 * The single place entities become API JSON. Hand-written rather than
 * serializer-driven so the shape the React app relies on is explicit and
 * nothing (password hashes, lazy collections) leaks by accident.
 */
final class Presenter
{
    public static function brand(Brand $brand): array
    {
        return ['id' => $brand->getId(), 'name' => $brand->getName()];
    }

    public static function type(StrainType $type): array
    {
        return ['id' => $type->getId(), 'name' => $type->getName(), 'position' => $type->getPosition()];
    }

    public static function terpene(Terpene $terpene): array
    {
        return [
            'id' => $terpene->getId(),
            'name' => $terpene->getName(),
            'aroma' => $terpene->getAroma(),
            'colour' => $terpene->getColour(),
        ];
    }

    public static function rating(Rating $rating): array
    {
        return [
            'id' => $rating->getId(),
            'label' => $rating->getLabel(),
            'score' => $rating->getScore(),
            'colour' => $rating->getColour(),
        ];
    }

    public static function strain(Strain $strain): array
    {
        return [
            'id' => $strain->getId(),
            'name' => $strain->getName(),
            'brand' => self::brand($strain->getBrand()),
            'type' => $strain->getType() ? self::type($strain->getType()) : null,
            'thcPercent' => null === $strain->getThcPercent() ? null : (float) $strain->getThcPercent(),
            'genetics' => $strain->getGenetics(),
            'price' => null === $strain->getPrice() ? null : (float) $strain->getPrice(),
            'aRating' => $strain->getARating() ? self::rating($strain->getARating()) : null,
            'mRating' => $strain->getMRating() ? self::rating($strain->getMRating()) : null,
            'notes' => $strain->getNotes(),
            'terpenes' => array_map(self::terpene(...), $strain->getTerpenes()->getValues()),
            'createdAt' => $strain->getCreatedAt()->format(\DATE_ATOM),
            'updatedAt' => $strain->getUpdatedAt()->format(\DATE_ATOM),
        ];
    }

    public static function user(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'displayName' => $user->getDisplayName(),
            'isAdmin' => $user->isAdmin(),
        ];
    }
}
