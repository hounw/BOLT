<?php

namespace App\Enums;

enum KnowledgeArticleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
