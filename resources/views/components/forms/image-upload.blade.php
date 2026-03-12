@props([
    'label',
    'urlModel',
    'currentUrl' => '',
    'required' => false,
    'helpText' => null,
])

<div 
    class="space-y-3"
    x-data="{
        uploading: false,
        progress: 0,
        error: '',
        
        async uploadImage(file) {
            if (!file || uploading) return;
            
            this.uploading = true;
            this.error = '';
            this.progress = 0;
            
            try {
                // Get presigned upload URL
                const response = await fetch('{{ route('landing-pages.images.presign') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({
                        file_name: file.name,
                        file_size: file.size,
                        mime_type: file.type,
                    }),
                });
                
                if (!response.ok) {
                    const data = await response.json();
                    throw new Error(data.message || 'Failed to get upload URL');
                }
                
                const { upload_url, upload_headers, public_url } = await response.json();
                
                // Upload directly to S3
                const uploadResponse = await fetch(upload_url, {
                    method: 'PUT',
                    headers: {
                        ...upload_headers,
                        'Content-Type': file.type,
                    },
                    body: file,
                });
                
                if (!uploadResponse.ok) {
                    throw new Error('Failed to upload file to S3');
                }
                
                // Set the public URL in the form
                @this.set('{{ $urlModel }}', public_url);
                
            } catch (err) {
                this.error = err.message || 'Upload failed';
                console.error('Upload error:', err);
            } finally {
                this.uploading = false;
                this.progress = 0;
            }
        }
    }"
>
    <flux:input 
        wire:model="{{ $urlModel }}" 
        :label="$label" 
        type="url" 
        :required="$required" 
    />

    <flux:field>
        <flux:label>{{ __('Upload Image') }}</flux:label>
        <input
            type="file"
            accept="image/*"
            class="block w-full cursor-pointer rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-700 file:mr-3 file:cursor-pointer file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-zinc-200 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:file:bg-zinc-800 dark:hover:file:bg-zinc-700"
            x-on:change="uploadImage($event.target.files[0])"
            :disabled="uploading"
        >
        
        <div x-show="uploading" class="mt-2">
            <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                <div 
                    class="h-full bg-indigo-600 transition-all duration-300"
                    x-bind:style="`width: ${progress}%`"
                ></div>
            </div>
            <p class="mt-1 text-xs text-zinc-500">Uploading...</p>
        </div>
        
        <p x-show="error" x-text="error" class="mt-1 text-xs text-red-600"></p>
    </flux:field>

    @if (is_string($currentUrl) && $currentUrl !== '')
        <div class="h-24 max-h-24 overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-950">
            <img src="{{ $currentUrl }}" alt="{{ $label }}" class="h-full max-h-24 w-full object-contain" style="max-height: 96px;">
        </div>

        <div class="flex items-center justify-end">
            <flux:button type="button" variant="ghost" size="sm" x-on:click.prevent="$wire.set('{{ $urlModel }}', '')">
                {{ __('Clear Image') }}
            </flux:button>
        </div>
    @endif

    @if (is_string($helpText) && $helpText !== '')
        <flux:text class="text-xs text-zinc-500">{{ $helpText }}</flux:text>
    @endif
</div>
