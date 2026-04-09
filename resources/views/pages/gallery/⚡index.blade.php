<?php

use App\Http\Requests\PresignGalleryAssetsRequest;
use App\Models\MediaAsset;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $kindFilter = 'all';

    public string $statusFilter = 'active';

    public string $search = '';

    public int $perPage = 25;

    public function updated(string $property): void
    {
        if (in_array($property, ['kindFilter', 'statusFilter', 'search', 'perPage'], true)) {
            if (! in_array($this->perPage, [25, 50, 100], true)) {
                $this->perPage = 25;
            }

            $this->resetPage();
        }
    }

    public function trash(int $assetId): void
    {
        $asset = MediaAsset::query()->findOrFail($assetId);
        $asset->delete();
    }

    public function restore(int $assetId): void
    {
        $asset = MediaAsset::withTrashed()->findOrFail($assetId);
        $asset->restore();
    }

    public function getExternalUrl(MediaAsset $asset): string
    {
        return $asset->public_url;
    }

    public function copyExternalUrl(int $assetId): void
    {
        $asset = MediaAsset::withTrashed()->findOrFail($assetId);

        $this->dispatch('gallery-url-copied', url: $asset->public_url);
    }

    public function configuredPublicBaseUrl(): string
    {
        return trim((string) config('filesystems.disks.s3.url'));
    }

    #[Computed]
    public function assets(): LengthAwarePaginator
    {
        $query = MediaAsset::query()
            ->latest('created_at')
            ->kind($this->kindFilter === 'all' ? null : $this->kindFilter)
            ->search(trim($this->search));

        if ($this->statusFilter === 'trashed') {
            $query->onlyTrashed();
        } else {
            $query->withoutTrashed();
        }

        $perPage = in_array($this->perPage, [25, 50, 100], true) ? $this->perPage : 25;

        return $query->paginate($perPage);
    }
}; ?>

<section class="w-full">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6 lg:p-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Gallery') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Upload and manage public media assets for HTML email embeds.') }}</flux:text>
            </div>
        </div>

        <div
            class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700"
            data-max-size-bytes="{{ PresignGalleryAssetsRequest::MAX_FILE_SIZE_BYTES }}"
            data-allowed-mime-types="{{ implode(',', PresignGalleryAssetsRequest::ALLOWED_MIME_TYPES) }}"
            x-data="galleryUploader({
                presignUrl: '{{ route('gallery.assets.presign') }}',
                finalizeUrl: '{{ route('gallery.assets.finalize') }}',
                csrfToken: '{{ csrf_token() }}',
                maxBytes: {{ PresignGalleryAssetsRequest::MAX_FILE_SIZE_BYTES }},
                allowedMimeTypes: @js(PresignGalleryAssetsRequest::ALLOWED_MIME_TYPES),
                refresh: () => $wire.$refresh(),
            })"
        >
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap items-center gap-2">
                    <input
                        x-ref="files"
                        type="file"
                        multiple
                        class="block rounded-md border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900"
                        accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml,application/pdf"
                    />

                    <flux:button type="button" variant="primary" x-on:click="upload" x-bind:disabled="uploading">
                        <span x-show="!uploading">{{ __('Upload files') }}</span>
                        <span x-show="uploading">{{ __('Uploading...') }}</span>
                    </flux:button>
                </div>

                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Allowed: JPG, PNG, WEBP, GIF, SVG, PDF. Max :max MB per file.', ['max' => (int) (PresignGalleryAssetsRequest::MAX_FILE_SIZE_BYTES / 1024 / 1024)]) }}
                </flux:text>
            </div>

            <p x-show="message !== ''" x-text="message" class="mt-3 text-sm text-zinc-600 dark:text-zinc-300"></p>

            @if ($this->configuredPublicBaseUrl() === '')
                <div class="mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-200">
                    {{ __('S3 public base URL is not configured. Set filesystems.disks.s3.url or ensure bucket policy/public URL is configured for stable external links.') }}
                </div>
            @endif
        </div>

        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="grid gap-3 lg:grid-cols-4">
                <div>
                    <label for="gallery-kind" class="mb-1 block text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Type') }}</label>
                    <select id="gallery-kind" wire:model.live="kindFilter" class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900">
                        <option value="all">{{ __('All') }}</option>
                        <option value="image">{{ __('Image') }}</option>
                        <option value="pdf">{{ __('PDF') }}</option>
                    </select>
                </div>

                <div>
                    <label for="gallery-status" class="mb-1 block text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Status') }}</label>
                    <select id="gallery-status" wire:model.live="statusFilter" class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900">
                        <option value="active">{{ __('Active') }}</option>
                        <option value="trashed">{{ __('Trashed') }}</option>
                    </select>
                </div>

                <div class="lg:col-span-2">
                    <label for="gallery-search" class="mb-1 block text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Search') }}</label>
                    <input id="gallery-search" type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search by filename or external id') }}" class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900" />
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="py-2 pe-3">{{ __('Preview') }}</th>
                            <th class="py-2 pe-3">{{ __('Name') }}</th>
                            <th class="py-2 pe-3">{{ __('External ID') }}</th>
                            <th class="py-2 pe-3">{{ __('Type') }}</th>
                            <th class="py-2 pe-3">{{ __('Size') }}</th>
                            <th class="py-2 pe-3">{{ __('Uploaded') }}</th>
                            <th class="py-2">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->assets as $asset)
                            <tr class="border-b border-zinc-100 align-top dark:border-zinc-800" wire:key="gallery-asset-{{ $asset->id }}">
                                <td class="py-2 pe-3">
                                    @if ($asset->kind === \App\Models\MediaAsset::KIND_IMAGE)
                                        <img src="{{ $asset->public_url }}" alt="{{ $asset->original_name }}" class="h-10 w-10 rounded object-cover" loading="lazy" />
                                    @else
                                        <span class="inline-flex h-10 w-10 items-center justify-center rounded bg-zinc-100 text-xs font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-200">PDF</span>
                                    @endif
                                </td>
                                <td class="py-2 pe-3">{{ $asset->original_name }}</td>
                                <td class="py-2 pe-3 font-mono text-xs">{{ $asset->external_id }}</td>
                                <td class="py-2 pe-3">
                                    @if ($asset->kind === \App\Models\MediaAsset::KIND_IMAGE)
                                        <flux:badge color="sky" size="sm">{{ __('Image') }}</flux:badge>
                                    @else
                                        <flux:badge color="amber" size="sm">{{ __('PDF') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="py-2 pe-3">{{ number_format((int) $asset->size_bytes) }} B</td>
                                <td class="py-2 pe-3">{{ $asset->created_at?->diffForHumans() }}</td>
                                <td class="py-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <flux:button size="sm" variant="ghost" wire:click="copyExternalUrl({{ $asset->id }})">{{ __('Copy URL') }}</flux:button>
                                        <flux:button size="sm" variant="ghost" :href="$this->getExternalUrl($asset)" target="_blank" rel="noreferrer">{{ __('Open') }}</flux:button>

                                        @if ($statusFilter === 'trashed')
                                            <flux:button size="sm" variant="primary" wire:click="restore({{ $asset->id }})">{{ __('Restore') }}</flux:button>
                                        @else
                                            <flux:button size="sm" variant="danger" wire:click="trash({{ $asset->id }})">{{ __('Trash') }}</flux:button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="py-4" colspan="7">
                                    <flux:text>{{ __('No assets found for current filters.') }}</flux:text>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $this->assets->links() }}
            </div>
        </div>
    </div>
</section>

<script>
    function galleryUploader(config) {
        return {
            uploading: false,
            message: '',
            async upload() {
                const files = Array.from(this.$refs.files?.files ?? []);

                if (files.length === 0) {
                    this.message = 'Please choose at least one file.';

                    return;
                }

                const invalidFile = files.find((file) => !config.allowedMimeTypes.includes(file.type) || file.size > config.maxBytes);

                if (invalidFile) {
                    this.message = `Invalid file: ${invalidFile.name}. Check type and max 50MB limit.`;

                    return;
                }

                this.uploading = true;
                this.message = 'Preparing upload...';

                try {
                    const presignResponse = await fetch(config.presignUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': config.csrfToken,
                        },
                        body: JSON.stringify({
                            files: files.map((file) => ({
                                name: file.name,
                                size: file.size,
                                mime_type: file.type,
                            })),
                        }),
                    });

                    const presignPayload = await presignResponse.json();

                    if (!presignResponse.ok) {
                        this.message = presignPayload?.message ?? 'Unable to prepare upload URL.';

                        return;
                    }

                    const uploads = presignPayload.uploads ?? [];

                    if (uploads.length !== files.length) {
                        this.message = 'Upload handshake mismatch. Please retry.';

                        return;
                    }

                    for (let index = 0; index < uploads.length; index++) {
                        const upload = uploads[index];
                        const file = files[index];

                        this.message = `Uploading ${index + 1}/${uploads.length}: ${file.name}`;

                        const uploadResponse = await fetch(upload.upload_url, {
                            method: 'PUT',
                            headers: {
                                ...(upload.upload_headers ?? {}),
                                'Content-Type': file.type,
                            },
                            body: file,
                        });

                        if (!uploadResponse.ok) {
                            this.message = `Failed uploading ${file.name}.`;

                            return;
                        }
                    }

                    this.message = 'Finalizing uploads...';

                    const finalizeResponse = await fetch(config.finalizeUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': config.csrfToken,
                        },
                        body: JSON.stringify({
                            uploads: uploads.map((upload) => ({
                                name: upload.name,
                                path: upload.path,
                                disk: upload.disk,
                                mime_type: upload.mime_type,
                                size: upload.size,
                            })),
                        }),
                    });

                    const finalizePayload = await finalizeResponse.json();

                    if (!finalizeResponse.ok) {
                        this.message = finalizePayload?.message ?? 'Unable to finalize uploads.';

                        return;
                    }

                    this.$refs.files.value = '';
                    this.message = 'Upload completed.';

                    await config.refresh();
                } catch (error) {
                    this.message = 'Unexpected upload error. Please retry.';
                } finally {
                    this.uploading = false;
                }
            },
        };
    }

    window.addEventListener('gallery-url-copied', async (event) => {
        const url = event?.detail?.url;

        if (typeof url !== 'string' || url.length === 0) {
            return;
        }

        try {
            await navigator.clipboard.writeText(url);
        } catch (error) {
            console.error('Clipboard copy failed', error);
        }
    });
</script>
