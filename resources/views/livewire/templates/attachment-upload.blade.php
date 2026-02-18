<div
    class="space-y-4"
    x-data="{
        pendingFiles: [],
        uploading: false,
        requestError: null,
        totalSize: @entangle('totalSizeFormatted'),
        isOverLimit: @entangle('isOverLimit'),
        progressPercentage: @entangle('progressPercentage'),
        remainingSize: @entangle('remainingSize'),
        async addAllAttachments() {
            if (this.pendingFiles.length === 0 || this.uploading) {
                return;
            }

            this.uploading = true;
            this.requestError = null;

            try {
                const presignResponse = await fetch('{{ route('templates.attachments.presign') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({
                        files: this.pendingFiles.map((item) => ({
                            name: item.name,
                            size: item.size,
                            mime_type: item.type,
                        })),
                    }),
                });

                const presignPayload = await presignResponse.json();

                if (!presignResponse.ok) {
                    throw new Error(this.extractMessage(presignPayload, 'Unable to prepare uploads.'));
                }

                const uploads = presignPayload.uploads ?? [];

                for (const [index, upload] of uploads.entries()) {
                    const file = this.pendingFiles[index]?.file;

                    if (!file) {
                        throw new Error('Uploaded file is missing from queue. Please try again.');
                    }

                    const uploadHeaders = {
                        ...(upload.upload_headers ?? {}),
                    };

                    if (!Object.keys(uploadHeaders).some((key) => key.toLowerCase() === 'content-type')) {
                        uploadHeaders['Content-Type'] = file.type;
                    }

                    const objectUploadResponse = await fetch(upload.upload_url, {
                        method: upload.method ?? 'PUT',
                        headers: uploadHeaders,
                        body: file,
                    });

                    if (!objectUploadResponse.ok) {
                        throw new Error(`Upload failed for ${file.name}.`);
                    }
                }

                const finalizeResponse = await fetch('{{ route('templates.attachments.finalize') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({
                        uploads: uploads.map((upload) => ({
                            name: upload.name,
                            size: upload.size,
                            mime_type: upload.mime_type,
                            path: upload.path,
                            disk: upload.disk,
                        })),
                    }),
                });

                const finalizePayload = await finalizeResponse.json();

                if (!finalizeResponse.ok) {
                    throw new Error(this.extractMessage(finalizePayload, 'Unable to finalize uploads.'));
                }

                await $wire.call('appendUploadedAttachments', finalizePayload.attachments ?? []);
                this.clearPendingFiles();
            } catch (error) {
                this.requestError = error.message ?? 'Upload failed. Please try again.';
            } finally {
                this.uploading = false;
            }
        },
        queueFiles(event) {
            const selectedFiles = Array.from(event.target.files ?? []);

            const queued = selectedFiles.map((file, index) => ({
                id: `${Date.now()}-${index}`,
                file,
                name: file.name,
                size: file.size,
                type: file.type,
            }));

            this.pendingFiles = [...this.pendingFiles, ...queued];
            event.target.value = '';
        },
        removePendingFile(fileId) {
            this.pendingFiles = this.pendingFiles.filter((file) => file.id !== fileId);
        },
        clearPendingFiles() {
            this.pendingFiles = [];
        },
        async removeAttachment(attachment) {
            try {
                const response = await fetch('{{ route('templates.attachments.delete') }}', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({
                        path: attachment.path,
                        disk: attachment.disk,
                    }),
                });

                const payload = await response.json();

                if (!response.ok) {
                    throw new Error(this.extractMessage(payload, 'Unable to remove attachment.'));
                }

                await $wire.call('removeUploadedAttachment', attachment.id);
            } catch (error) {
                this.requestError = error.message ?? 'Unable to remove attachment.';
            }
        },
        formatSize(sizeInBytes) {
            if (!sizeInBytes) {
                return '0 MB';
            }

            return `${(sizeInBytes / 1024 / 1024).toFixed(2)} MB`;
        },
        extractMessage(payload, fallback) {
            if (payload?.message) {
                return payload.message;
            }

            const errors = payload?.errors;
            if (errors && typeof errors === 'object') {
                const firstField = Object.keys(errors)[0];
                if (firstField && Array.isArray(errors[firstField]) && errors[firstField][0]) {
                    return errors[firstField][0];
                }
            }

            return fallback;
        }
    }"
>
    <div class="rounded-lg border p-4" :class="isOverLimit ? 'border-red-300 bg-red-50 dark:border-red-700 dark:bg-red-900/20' : 'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/50'">
        <div class="mb-2 flex items-center justify-between">
            <span class="text-sm font-medium" :class="isOverLimit ? 'text-red-700 dark:text-red-400' : 'text-zinc-700 dark:text-zinc-300'">
                Total Attachment Size
            </span>
            <span class="text-sm font-semibold" :class="isOverLimit ? 'text-red-700 dark:text-red-400' : 'text-zinc-900 dark:text-zinc-100'">
                <span x-text="totalSize"></span> / 40 MB
            </span>
        </div>

        <div class="h-2 w-full rounded-full bg-zinc-200 dark:bg-zinc-700">
            <div
                class="h-2 rounded-full transition-all duration-300"
                :class="isOverLimit ? 'bg-red-500' : (progressPercentage > 80 ? 'bg-yellow-500' : 'bg-blue-500')"
                :style="`width: ${progressPercentage}%`"
            ></div>
        </div>

        <div x-show="isOverLimit" x-transition class="mt-2 text-sm text-red-600 dark:text-red-400">
            <flux:icon.exclamation-triangle class="mr-1 inline h-4 w-4" />
            Total attachment size exceeds 40MB limit. Please remove some files to continue.
        </div>

        <div x-show="!isOverLimit && remainingSize < 10485760" x-transition class="mt-2 text-sm text-yellow-600 dark:text-yellow-400">
            <flux:icon.information-circle class="mr-1 inline h-4 w-4" />
            Less than 10MB remaining
        </div>
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Upload Attachments</label>
        <input
            type="file"
            multiple
            accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx"
            class="block w-full cursor-pointer rounded-lg border border-zinc-300 p-2 text-sm text-zinc-700 file:mr-3 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-zinc-200 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:file:bg-zinc-800 dark:hover:file:bg-zinc-700"
            x-on:change="queueFiles($event)"
        >
        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
            Allowed files: PDF, Word (DOC, DOCX), Excel (XLS, XLSX), PowerPoint (PPT, PPTX). Max 40MB total.
        </p>

        @error('attachments')
            <div class="mt-2 rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/20">
                <div class="flex items-start text-red-700 dark:text-red-400">
                    <flux:icon.exclamation-circle class="mt-0.5 mr-2 h-5 w-5 flex-shrink-0" />
                    <span class="text-sm">{{ $message }}</span>
                </div>
            </div>
        @enderror

        <div x-show="requestError" x-transition class="mt-2 rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/20">
            <div class="flex items-start text-red-700 dark:text-red-400">
                <flux:icon.exclamation-circle class="mt-0.5 mr-2 h-5 w-5 flex-shrink-0" />
                <span class="text-sm" x-text="requestError"></span>
            </div>
        </div>

        <template x-if="pendingFiles.length > 0">
            <div class="mt-3 space-y-3">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        Files ready to upload (<span x-text="pendingFiles.length"></span>):
                    </p>
                    <div class="flex gap-2">
                        <flux:button
                            type="button"
                            size="sm"
                            variant="primary"
                            icon="check"
                            x-bind:disabled="uploading || isOverLimit"
                            x-on:click="addAllAttachments"
                        >
                            <span x-show="!uploading">Add All</span>
                            <span x-show="uploading">Uploading...</span>
                        </flux:button>
                        <flux:button
                            type="button"
                            size="sm"
                            variant="ghost"
                            icon="x-mark"
                            x-bind:disabled="uploading"
                            x-on:click="clearPendingFiles"
                        >
                            Clear All
                        </flux:button>
                    </div>
                </div>

                <div class="space-y-2">
                    <template x-for="pendingFile in pendingFiles" :key="pendingFile.id">
                        <div class="flex items-center justify-between rounded-lg border border-zinc-200 bg-zinc-50 p-2 dark:border-zinc-700 dark:bg-zinc-800/50">
                            <div class="min-w-0 flex-1 items-center space-x-3">
                                <flux:icon.document class="h-5 w-5 flex-shrink-0 text-zinc-400" />
                                <span class="truncate text-sm text-zinc-700 dark:text-zinc-300" x-text="pendingFile.name"></span>
                                <span class="flex-shrink-0 text-xs text-zinc-500" x-text="`(${formatSize(pendingFile.size)})`"></span>
                            </div>
                            <flux:button
                                type="button"
                                size="sm"
                                variant="ghost"
                                icon="trash"
                                class="flex-shrink-0 text-red-600 hover:text-red-700"
                                x-bind:disabled="uploading"
                                x-on:click="removePendingFile(pendingFile.id)"
                            />
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>

    @if (count($attachments) > 0)
        <div class="space-y-2">
            <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Attached Files ({{ count($attachments) }}):</p>

            @foreach ($attachments as $attachment)
                <div class="flex items-center justify-between rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-800">
                    <div class="min-w-0 items-center space-x-3">
                        <flux:icon.document-text class="h-5 w-5 flex-shrink-0 text-blue-500" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $attachment['name'] }}</p>
                            <p class="text-xs text-zinc-500">
                                {{ round($attachment['size'] / 1024 / 1024, 2) }} MB
                                @if (isset($attachment['uploaded_at']))
                                    • {{ \Carbon\Carbon::parse($attachment['uploaded_at'])->diffForHumans() }}
                                @endif
                            </p>
                        </div>
                    </div>

                    <flux:button
                        type="button"
                        size="sm"
                        variant="ghost"
                        icon="trash"
                        class="text-red-600 hover:text-red-700"
                        x-on:click="removeAttachment({
                            id: @js($attachment['id'] ?? ''),
                            path: @js($attachment['path'] ?? ''),
                            disk: @js($attachment['disk'] ?? config('filesystems.default'))
                        })"
                    />
                </div>
            @endforeach
        </div>
    @else
        <div class="rounded-lg border-2 border-dashed border-zinc-300 py-6 text-center dark:border-zinc-700">
            <flux:icon.document class="mx-auto mb-2 h-10 w-10 text-zinc-400" />
            <p class="text-sm text-zinc-500 dark:text-zinc-400">No attachments yet</p>
        </div>
    @endif
</div>
