@props(['attachments', 'attachableType', 'attachableId'])

@can('viewAny', App\Models\Attachment::class)
    <x-panel>
        <h2 class="font-semibold">Files</h2>

        <div class="mt-4 divide-y divide-zinc-100 text-sm">
            @forelse ($attachments as $attachment)
                <div class="flex items-center justify-between gap-4 py-3">
                    <div>
                        <div class="font-medium">{{ $attachment->original_name }}</div>
                        <div class="text-zinc-500">{{ number_format($attachment->size / 1024, 1) }} KB @if ($attachment->uploader) - {{ $attachment->uploader->name }} @endif</div>
                    </div>
                    <a href="{{ route('attachments.download', $attachment) }}" class="rounded-md border border-zinc-300 px-3 py-2 text-sm font-semibold">Download</a>
                </div>
            @empty
                <p class="py-6 text-zinc-500">No files attached.</p>
            @endforelse
        </div>

        @can('create', App\Models\Attachment::class)
            <form method="POST" action="{{ route('attachments.store') }}" enctype="multipart/form-data" class="mt-5 grid gap-3 border-t border-zinc-100 pt-5">
                @csrf
                <input type="hidden" name="attachable_type" value="{{ $attachableType }}">
                <input type="hidden" name="attachable_id" value="{{ $attachableId }}">
                <label class="block text-sm font-medium">Upload file<input name="file" type="file" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                <div class="flex justify-end"><button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Upload</button></div>
            </form>
        @endcan
    </x-panel>
@endcan
