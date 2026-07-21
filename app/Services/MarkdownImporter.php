<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\KnowledgeArticle;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class MarkdownImporter
{
    public const MAX_KILOBYTES = 1024;

    /**
     * @return array{body_markdown: string, suggested_title: string}
     */
    public function read(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['md', 'markdown'], true)) {
            throw ValidationException::withMessages(['source_markdown' => 'Choose a Markdown file ending in .md or .markdown.']);
        }

        $contents = file_get_contents($file->getRealPath());
        if ($contents === false || trim($contents) === '') {
            throw ValidationException::withMessages(['source_markdown' => 'The Markdown file is empty or could not be read.']);
        }

        if (! mb_check_encoding($contents, 'UTF-8')) {
            throw ValidationException::withMessages(['source_markdown' => 'The Markdown file must use UTF-8 text encoding.']);
        }

        preg_match('/^\s*#\s+(.+?)\s*#*\s*$/m', $contents, $heading);
        $fallback = str(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
            ->replace(['-', '_'], ' ')
            ->squish()
            ->title()
            ->toString();
        $title = isset($heading[1])
            ? str($heading[1])->replaceMatches('/[*_`\[\]]/', '')->squish()->limit(180, '')->toString()
            : $fallback;

        return [
            'body_markdown' => $contents,
            'suggested_title' => $title ?: 'Imported article',
        ];
    }

    public function attach(KnowledgeArticle $article, UploadedFile $file, User $user): Attachment
    {
        $path = $file->store('attachments/'.now()->format('Y/m'), 'local');

        return Attachment::create([
            'attachable_type' => $article->getMorphClass(),
            'attachable_id' => $article->getKey(),
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'metadata' => ['kind' => 'knowledge_source'],
            'uploaded_by_id' => $user->id,
        ]);
    }
}
