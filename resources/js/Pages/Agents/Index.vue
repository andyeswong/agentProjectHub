<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'

defineProps({ agents: Array })

const providerColor = {
    anthropic: '#c084fc',
    openai:    '#10b981',
    ollama:    '#f59e0b',
    gemini:    '#4285f4',
    custom:    'var(--color-neutral)',
}
</script>

<template>
    <AppLayout>
        <div class="space-y-5">
            <h1 class="text-xl font-semibold" style="color: var(--color-text-primary);">Agent Map</h1>

            <div v-if="agents.length === 0" class="rounded-lg p-10 text-center text-sm"
                style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border); color: var(--color-text-muted);">
                No agents registered yet.
            </div>

            <div v-else class="rounded-lg overflow-hidden" style="border: 1px solid var(--color-surface-border);">
                <table class="w-full text-sm">
                    <thead>
                        <tr style="background-color: var(--color-surface-elevated); border-bottom: 1px solid var(--color-surface-border);">
                            <th class="text-left px-4 py-3 text-xs font-medium" style="color: var(--color-text-muted);">Model</th>
                            <th class="text-left px-4 py-3 text-xs font-medium" style="color: var(--color-text-muted);">Provider</th>
                            <th class="text-left px-4 py-3 text-xs font-medium" style="color: var(--color-text-muted);">Client</th>
                            <th class="text-left px-4 py-3 text-xs font-medium" style="color: var(--color-text-muted);">Pilot</th>
                            <th class="text-left px-4 py-3 text-xs font-medium" style="color: var(--color-text-muted);">Permissions</th>
                            <th class="text-left px-4 py-3 text-xs font-medium" style="color: var(--color-text-muted);">Last Active</th>
                            <th class="text-left px-4 py-3 text-xs font-medium" style="color: var(--color-text-muted);">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="agent in agents" :key="agent.id"
                            class="border-b transition-colors"
                            :style="{ borderColor: 'var(--color-surface-border)', opacity: agent.is_revoked ? '0.4' : '1', backgroundColor: 'var(--color-surface-elevated)' }"
                            onmouseover="this.style.backgroundColor='var(--color-surface-hover)'"
                            onmouseout="this.style.backgroundColor='var(--color-surface-elevated)'"
                        >
                            <td class="px-4 py-3">
                                <span class="font-medium" style="font-family: var(--font-mono); color: var(--color-text-primary);">{{ agent.model ?? '—' }}</span>
                                <p v-if="agent.system_prompt_hash" class="text-xs mt-0.5" style="color: var(--color-text-muted); font-family: var(--font-mono);">
                                    {{ agent.system_prompt_hash }}
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs px-2 py-0.5 rounded-full"
                                    :style="{ backgroundColor: (providerColor[agent.model_provider] ?? 'var(--color-neutral)') + '20', color: providerColor[agent.model_provider] ?? 'var(--color-neutral)' }">
                                    {{ agent.model_provider ?? '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs" style="color: var(--color-text-secondary); font-family: var(--font-mono);">{{ agent.client_type }}</td>
                            <td class="px-4 py-3">
                                <p class="text-xs" style="color: var(--color-text-primary);">{{ agent.pilot ?? '—' }}</p>
                                <p v-if="agent.pilot_contact" class="text-xs" style="color: var(--color-text-muted);">{{ agent.pilot_contact }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <span v-for="perm in (agent.permissions ?? [])" :key="perm"
                                        class="text-xs px-1.5 py-0.5 rounded"
                                        style="background-color: var(--color-surface-border); color: var(--color-text-secondary); font-family: var(--font-mono);">
                                        {{ perm }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <span v-if="agent.last_active_at" style="color: var(--color-text-secondary);">{{ agent.last_active_ago }}</span>
                                <span v-else style="color: var(--color-text-muted);">Never</span>
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="agent.is_revoked" class="text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(239,68,68,0.1); color: var(--color-danger);">revoked</span>
                                <div v-else class="flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full" style="background-color: var(--color-success);"></span>
                                    <span class="text-xs" style="color: var(--color-success);">active</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
