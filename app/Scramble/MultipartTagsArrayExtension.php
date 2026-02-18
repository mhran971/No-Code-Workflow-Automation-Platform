<?php

namespace App\Scramble;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;

/**
 * Expands the "tags" array body parameter into tags[0], tags[1], tags[2]
 * so the Try It UI sends multipart form data in the same way as Postman
 * (tags[0]=cv&tags[1]=cv2) and the request validates correctly.
 */
class MultipartTagsArrayExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        if (strtoupper($routeInfo->method) !== 'POST') {
            return;
        }

        if (! str_contains($routeInfo->route->uri(), 'documents') || $operation->requestBodyObject === null) {
            return;
        }

        $content = $operation->requestBodyObject->content['multipart/form-data'] ?? null;
        if (! $content instanceof Schema) {
            return;
        }

        $type = $content->type;
        if (! $type instanceof ObjectType || ! $type->hasProperty('tags')) {
            return;
        }

        // Replace single "tags" (array) with tags[0], tags[1], tags[2] so the docs Try It sends indexed form fields
        unset($type->properties['tags']);
        $type->required = array_values(array_filter($type->required, fn (string $name) => $name !== 'tags'));

        $tag0 = (new StringType)->setDescription('Tag 1 (at least one required). Add more as tags[1], tags[2], …');
        $type->addProperty('tags[0]', $tag0);
        $type->addRequired(['tags[0]']);

        $tag1 = (new StringType)->setDescription('Tag 2');
        $type->addProperty('tags[1]', $tag1);

        $tag2 = (new StringType)->setDescription('Tag 3. Add more with tags[4], tags[5], …');
        $type->addProperty('tags[2]', $tag2);
    }
}
