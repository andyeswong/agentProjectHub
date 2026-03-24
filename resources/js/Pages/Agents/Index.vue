<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import InviteAgentPanel from '@/Components/InviteAgentPanel.vue'
import { ref } from 'vue'

defineProps({ agents: Array })

const inviteOpen = ref(false)

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

            <!-- Header -->
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold" style="color: var(--color-text-primary);">Agent Map</h1>

                <button @click="inviteOpen = !inviteOpen"
                    class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-all"
                    :style="inviteOpen
                        ? 'background-color: rgba(56,189,248,0.15); color: var(--color-accent); border: 1px solid rgba(56,189,248,0.3);'
                        : 'background-color: var(--color-surface-elevated); color: var(--color-text-secondary); border: 1px solid var(--color-surface-border);'">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    <span class="hidden sm:inline">Invite Agent</span>
                    <span class="sm:hidden">Invite</span>
                </button>
            </div>

            <!-- Invite panel -->
            <InviteAgentPanel v-if="inviteOpen" />

            <!-- Empty state -->
            <div v-if="agents.length === 0" class="rounded-lg p-10 text-center text-sm"
                style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border); color: var(--color-text-muted);">
                No agents registered yet.
            </div>

            <template v-else>

                <!-- Mobile: card list (hidden on md+) -->
                <div class="md:hidden space-y-3">
                    <div v-for="agent in agents" :key="agent.id"
                        class="rounded-lg p-4 space-y-3"
                        :style="{ backgroundColor: 'var(--color-surface-elevated)', border: '1px solid var(--color-surface-border)', opacity: agent.is_revoked ? '0.5' : '1' }">

                        <!-- Model + status row -->
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-medium" style="font-family: var(--font-mono); color: var(--color-text-primary);">{{ agent.model ?? '—' }}</p>
                                <p v-if="agent.system_prompt_hash" class="text-xs mt-0.5" style="color: var(--color-text-muted); font-family: var(--font-mono);">{{ agent.system_prompt_hash }}</p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-xs px-2 py-0.5 rounded-full"
                                    :style="{ backgroundColor: (providerColor[agent.model_provider] ?? 'var(--color-neutral)') + '20', color: providerColor[agent.model_provider] ?? 'var(--color-neutral)' }">
                                    {{ agent.model_provider ?? '—' }}
                                </span>
                                <span v-if="agent.is_revoked" class="text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(239,68,68,0.1); color: var(--color-danger);">revoked</span>
                                <div v-else class="flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full" style="background-color: var(--color-success);"></span>
                                    <span class="text-xs" style="color: var(--color-success);">active</span>
                                </div>
                            </div>
                        </div>

                        <!-- Pilot + client -->
                        <div class="flex items-center justify-between text-xs">
                            <div>
                                <span style="color: var(--color-text-muted);">Pilot: </span>
                                <span style="color: var(--color-text-primary);">{{ agent.pilot ?? '—' }}</span>
                                <span v-if="agent.pilot_contact" class="ml-1" style="color: var(--color-text-muted);">· {{ agent.pilot_contact }}</span>
                            </div>
                            <span style="color: var(--color-text-secondary); font-family: var(--font-mono);">{{ agent.client_type }}</span>
                        </div>

                        <!-- Permissions -->
                        <div v-if="agent.permissions?.length" class="flex flex-wrap gap-1">
                            <span v-for="perm in agent.permissions" :key="perm"
                                class="text-xs px-1.5 py-0.5 rounded"
                                style="background-color: var(--color-surface-border); color: var(--color-text-secondary); font-family: var(--font-mono);">
                                {{ perm }}
                            </span>
                        </div>

                        <!-- Last active -->
                        <p class="text-xs" style="color: var(--color-text-muted);">
                            Last active:
                            <span v-if="agent.last_active_at" style="color: var(--color-text-secondary);">{{ agent.last_active_ago }}</span>
                            <span v-else>Never</span>
                        </p>
                    </div>
                </div>

                <!-- Desktop: table (hidden on mobile) -->
                <div class="hidden md:block rounded-lg overflow-hidden" style="border: 1px solid var(--color-surface-border);">
                    <div class="overflow-x-auto">
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
                                        <p v-if="agent.system_prompt_hash" class="text-xs mt-0.5" style="color: var(--color-text-muted); font-family: var(--font-mono);">{{ agent.system_prompt_hash }}</p>
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

            </template>
        </div>
    </AppLayout>
</template>
