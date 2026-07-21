<x-layouts.app>
    <section class="mx-auto w-full max-w-7xl px-6 py-8">
        <x-page-header :title="$asset->name" :description="collect($asset->tags ?? [])->implode(', ') ?: 'Asset record'">
            <x-slot:action>
                @can('update', $asset)
                    <a href="{{ route('assets.edit', $asset) }}" class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Edit</a>
                @endcan
            </x-slot:action>
        </x-page-header>

        @if ($errors->any())
            <div class="mt-6 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(360px,0.8fr)]">
            <div class="grid gap-6">
                <x-panel>
                    <div class="grid gap-5 md:grid-cols-[220px_1fr]">
                        <x-asset-photo :asset="$asset" size="lg" />
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-zinc-100 px-3 py-1 text-xs font-semibold text-zinc-700">{{ str($asset->status?->value)->title() }}</span>
                                @if ($asset->currentAssignment?->employee)
                                    <span class="rounded-full bg-zinc-950 px-3 py-1 text-xs font-semibold text-white">With {{ $asset->currentAssignment->employee->full_name }}</span>
                                @endif
                            </div>

                            <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                                <div><dt class="text-zinc-500">System ID</dt><dd class="font-medium">{{ $asset->asset_tag }}</dd></div>
                                <div><dt class="text-zinc-500">Tags</dt><dd class="font-medium">{{ collect($asset->tags ?? [])->implode(', ') ?: '-' }}</dd></div>
                                <div><dt class="text-zinc-500">Serial</dt><dd class="font-medium">{{ $asset->serial_number ?: '-' }}</dd></div>
                                <div><dt class="text-zinc-500">Vendor</dt><dd class="font-medium">{{ $asset->vendor ?: '-' }}</dd></div>
                                <div><dt class="text-zinc-500">Purchase date</dt><dd class="font-medium">{{ $asset->purchase_date?->toDateString() ?: '-' }}</dd></div>
                                <div><dt class="text-zinc-500">Cost</dt><dd class="font-medium">@if ($asset->purchase_cost) {{ $asset->currency }} {{ $asset->purchase_cost }} @else - @endif</dd></div>
                                <div><dt class="text-zinc-500">Warranty</dt><dd class="font-medium">{{ $asset->warranty_expires_on?->toDateString() ?: '-' }}</dd></div>
                                <div><dt class="text-zinc-500">Current holder</dt><dd class="font-medium">{{ $asset->currentAssignment?->employee?->full_name ?: 'Unassigned' }}</dd></div>
                            </dl>
                        </div>
                    </div>
                </x-panel>

                <x-panel>
                    <h2 class="font-semibold">Asset history</h2>
                    <div class="mt-4 divide-y divide-zinc-100 text-sm">
                        @forelse ($asset->events->sortByDesc('occurred_at') as $event)
                            <article class="py-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-semibold">{{ App\Models\AssetEvent::TYPES[$event->type] ?? str($event->type)->title() }}</h3>
                                        <p class="mt-1 text-zinc-500">
                                            {{ $event->occurred_at?->toDayDateTimeString() }}
                                            @if ($event->actor)
                                                - {{ $event->actor->name }}
                                            @endif
                                        </p>
                                    </div>
                                    @if ($event->condition)
                                        <span class="rounded-full bg-zinc-100 px-3 py-1 text-xs font-semibold text-zinc-700">{{ $event->condition }}</span>
                                    @endif
                                </div>

                                @if ($event->fromEmployee || $event->employee)
                                    <p class="mt-2 text-zinc-600">
                                        @if ($event->fromEmployee)
                                            From {{ $event->fromEmployee->full_name }}
                                        @endif
                                        @if ($event->employee)
                                            @if ($event->fromEmployee) to @else To @endif {{ $event->employee->full_name }}
                                        @endif
                                    </p>
                                @endif

                                @if ($event->notes)
                                    <p class="mt-2 text-zinc-700">{{ $event->notes }}</p>
                                @endif

                                @if ($event->attachments->isNotEmpty())
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach ($event->attachments as $attachment)
                                            <a href="{{ route('attachments.download', $attachment) }}" class="rounded-md border border-zinc-300 px-3 py-2 text-xs font-semibold">{{ $attachment->original_name }}</a>
                                        @endforeach
                                    </div>
                                @endif
                            </article>
                        @empty
                            <p class="py-6 text-zinc-500">No history yet.</p>
                        @endforelse
                    </div>
                </x-panel>

                <div>
                    <x-attachments-panel :attachments="$asset->attachments" attachable-type="assets" :attachable-id="$asset->id" />
                </div>
            </div>

            <div class="grid content-start gap-6">
                @can('assign', $asset)
                    <x-panel>
                        <h2 class="font-semibold">Transfer holder</h2>
                        <form method="POST" action="{{ route('assets.assign', $asset) }}" enctype="multipart/form-data" class="mt-4 grid gap-3">
                            @csrf
                            <select name="employee_id" required class="rounded-md border border-zinc-300 px-3 py-2">
                                <option value="">Assign to employee</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                                @endforeach
                            </select>
                            <input name="condition" placeholder="Condition, e.g. mint condition" class="rounded-md border border-zinc-300 px-3 py-2">
                            <textarea name="notes" rows="3" placeholder="Transfer notes" class="rounded-md border border-zinc-300 px-3 py-2"></textarea>
                            <label class="block text-sm font-medium">Photos or files<input name="files[]" type="file" multiple class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                            <button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Save transfer</button>
                        </form>

                        <form method="POST" action="{{ route('assets.return', $asset) }}" enctype="multipart/form-data" class="mt-5 grid gap-3 border-t border-zinc-100 pt-5">
                            @csrf
                            <input name="condition" placeholder="Return condition" class="rounded-md border border-zinc-300 px-3 py-2">
                            <textarea name="notes" rows="3" placeholder="Return notes" class="rounded-md border border-zinc-300 px-3 py-2"></textarea>
                            <label class="block text-sm font-medium">Photos or files<input name="files[]" type="file" multiple class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                            <button class="rounded-md border border-zinc-300 px-4 py-2 text-sm font-semibold">Mark returned</button>
                        </form>
                    </x-panel>

                    <x-panel>
                        <h2 class="font-semibold">Add history entry</h2>
                        <form method="POST" action="{{ route('assets.events.store', $asset) }}" enctype="multipart/form-data" class="mt-4 grid gap-3">
                            @csrf
                            <select name="type" required class="rounded-md border border-zinc-300 px-3 py-2">
                                @foreach ($eventTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <input name="occurred_at" type="datetime-local" class="rounded-md border border-zinc-300 px-3 py-2">
                            <select name="employee_id" class="rounded-md border border-zinc-300 px-3 py-2">
                                <option value="">No employee</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                                @endforeach
                            </select>
                            <input name="condition" placeholder="Condition" class="rounded-md border border-zinc-300 px-3 py-2">
                            <textarea name="notes" rows="4" required placeholder="History note" class="rounded-md border border-zinc-300 px-3 py-2"></textarea>
                            <label class="block text-sm font-medium">Photos or files<input name="files[]" type="file" multiple class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                            <button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Add entry</button>
                        </form>
                    </x-panel>
                @endcan

                <x-panel>
                    <h2 class="font-semibold">Assignment history</h2>
                    <div class="mt-4 divide-y divide-zinc-100 text-sm">
                        @forelse ($asset->assignments as $assignment)
                            <div class="py-3">
                                <div class="font-medium">{{ $assignment->employee?->full_name }}</div>
                                <div class="text-zinc-500">{{ $assignment->assigned_at?->toDateString() }} @if ($assignment->returned_at) to {{ $assignment->returned_at->toDateString() }} @endif</div>
                                @if ($assignment->notes)
                                    <p class="mt-1 text-zinc-600">{{ $assignment->notes }}</p>
                                @endif
                            </div>
                        @empty
                            <p class="py-6 text-zinc-500">No assignments.</p>
                        @endforelse
                    </div>
                </x-panel>
            </div>
        </div>
    </section>
</x-layouts.app>
