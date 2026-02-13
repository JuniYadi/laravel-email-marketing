<div class="space-y-4" x-data="{ 
    totalSize: @entangle('totalSizeFormatted'),
    isOverLimit: @entangle('isOverLimit'),
    progressPercentage: @entangle('progressPercentage'),
    remainingSize: @entangle('remainingSize')
}">
    {{-- Size limit indicator --}}
    <div class="rounded-lg border p-4" :class="isOverLimit ? 'border-red-300 bg-red-50 dark:border-red-700 dark:bg-red-900/20' : 'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/50'">
        <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium" :class="isOverLimit ? 'text-red-700 dark:text-red-400' : 'text-zinc-700 dark:text-zinc-300'">
                Total Attachment Size
            </span>
            <span class="text-sm font-semibold" :class="isOverLimit ? 'text-red-700 dark:text-red-400' : 'text-zinc-900 dark:text-zinc-100'">
                <span x-text="totalSize"></span> / 40 MB
            </span>
        </div>
        
        {{-- Progress bar --}}
        <div class="w-full h-2 bg-zinc-200 rounded-full dark:bg-zinc-700">
            <div 
                class="h-2 rounded-full transition-all duration-300"
                :class="isOverLimit ? 'bg-red-500' : (progressPercentage > 80 ? 'bg-yellow-500' : 'bg-blue-500')"
                :style="`width: ${progressPercentage}%`"
            ></div>
        </div>

        {{-- Warning message --}}
        <div x-show="isOverLimit" x-transition class="mt-2 text-sm text-red-600 dark:text-red-400">
            <flux:icon.exclamation-triangle class="inline w-4 h-4 mr-1" />
            Total attachment size exceeds 40MB limit. Please remove some files to continue.
        </div>

        {{-- Remaining space info --}}
        <div x-show="!isOverLimit && remainingSize < 10485760" x-transition class="mt-2 text-sm text-yellow-600 dark:text-yellow-400">
            <flux:icon.information-circle class="inline w-4 h-4 mr-1" />
            Less than 10MB remaining
        </div>
    </div>

    {{-- File upload input --}}
    <div>
        <flux:input 
            type="file" 
            wire:model="newAttachments" 
            label="Upload Attachments"
            description="Allowed files: PDF, Word (DOC, DOCX), Excel (XLS, XLSX), PowerPoint (PPT, PPTX). Max 40MB total."
            multiple
            accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx"
        />
        
        @error('newAttachments.*')
            <div class="mt-2 p-3 bg-red-50 border border-red-200 rounded-lg dark:bg-red-900/20 dark:border-red-800">
                <div class="flex items-start text-red-700 dark:text-red-400">
                    <flux:icon.exclamation-circle class="w-5 h-5 mr-2 flex-shrink-0 mt-0.5" />
                    <span class="text-sm">{{ $message }}</span>
                </div>
            </div>
        @enderror

        {{-- Pending uploads --}}
        @if (count($newAttachments) > 0)
            <div class="mt-3 space-y-3">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        Files ready to upload ({{ count($newAttachments) }}):
                    </p>
                    <div class="flex gap-2">
                        <flux:button 
                            wire:click="addAllAttachments" 
                            size="sm" 
                            variant="primary"
                            icon="check"
                            :disabled="$this->isOverLimit"
                        >
                            Add All
                        </flux:button>
                        <flux:button 
                            wire:click="clearNewAttachments" 
                            size="sm" 
                            variant="ghost"
                            icon="x-mark"
                        >
                            Clear All
                        </flux:button>
                    </div>
                </div>

                @error('newAttachments')
                    <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg dark:bg-yellow-900/20 dark:border-yellow-800">
                        <div class="flex items-start text-yellow-700 dark:text-yellow-400">
                            <flux:icon.exclamation-triangle class="w-5 h-5 mr-2 flex-shrink-0 mt-0.5" />
                            <span class="text-sm">{{ $message }}</span>
                        </div>
                    </div>
                @enderror

                <div class="space-y-2">
                    @foreach ($newAttachments as $index => $file)
                        <div class="flex items-center justify-between p-2 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                            <div class="flex items-center space-x-3 min-w-0 flex-1">
                                <flux:icon.document class="w-5 h-5 text-zinc-400 flex-shrink-0" />
                                <span class="text-sm text-zinc-700 dark:text-zinc-300 truncate">{{ $file->getClientOriginalName() }}</span>
                                <span class="text-xs text-zinc-500 flex-shrink-0">({{ round($file->getSize() / 1024 / 1024, 2) }} MB)</span>
                            </div>
                            <flux:button 
                                wire:click="removeNewAttachment({{ $index }})" 
                                size="sm" 
                                variant="ghost"
                                icon="trash"
                                class="text-red-600 hover:text-red-700 flex-shrink-0"
                            />
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Attached files list --}}
    @if (count($attachments) > 0)
        <div class="space-y-2">
            <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Attached Files ({{ count($attachments) }}):</p>
            
            @foreach ($attachments as $attachment)
                <div class="flex items-center justify-between p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800">
                    <div class="flex items-center space-x-3 min-w-0">
                        <flux:icon.document-text class="w-5 h-5 text-blue-500 flex-shrink-0" />
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">{{ $attachment['name'] }}</p>
                            <p class="text-xs text-zinc-500">
                                {{ round($attachment['size'] / 1024 / 1024, 2) }} MB
                                @if (isset($attachment['uploaded_at']))
                                    • {{ \Carbon\Carbon::parse($attachment['uploaded_at'])->diffForHumans() }}
                                @endif
                            </p>
                        </div>
                    </div>
                    
                    <flux:button 
                        wire:click="removeAttachment('{{ $attachment['id'] }}')" 
                        size="sm" 
                        variant="ghost"
                        icon="trash"
                        class="text-red-600 hover:text-red-700"
                    />
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-6 border-2 border-dashed border-zinc-300 dark:border-zinc-700 rounded-lg">
            <flux:icon.document class="w-10 h-10 text-zinc-400 mx-auto mb-2" />
            <p class="text-sm text-zinc-500 dark:text-zinc-400">No attachments yet</p>
        </div>
    @endif
</div>