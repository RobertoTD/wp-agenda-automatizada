<?php
/**
 * AI Chat Assistant - Panel + FAB markup (skeleton, no wiring)
 *
 * Step 5.a: UI-only shell. Rendered via <template> tags so the DOM
 * stays inert until aichat.js mounts it. No backend contact, no nonces,
 * no fetch. sendMessage() is stubbed by mockSend() in aichat.js; 5.b
 * replaces that single seam with a real AJAX call.
 *
 * @package AgendaAutomatizada
 * @since 2.x
 */

defined('ABSPATH') or die('No direct access');
?>

<style>
/* ==== AI Chat Assistant (local, prefixed) ==== */

@keyframes aa-ai-chat-panel-in {
    from { opacity: 0; transform: translateY(8px) scale(0.96); }
    to   { opacity: 1; transform: translateY(0)    scale(1); }
}
@keyframes aa-ai-chat-panel-out {
    from { opacity: 1; transform: translateY(0)    scale(1); }
    to   { opacity: 0; transform: translateY(8px) scale(0.96); }
}
.aa-ai-chat-panel-enter {
    animation: aa-ai-chat-panel-in 150ms ease-out forwards;
    transform-origin: bottom right;
}
.aa-ai-chat-panel-leave {
    animation: aa-ai-chat-panel-out 150ms ease-in forwards;
    transform-origin: bottom right;
}

/* Typing indicator ("escribiendo…") */
@keyframes aa-ai-chat-dot-bounce {
    0%, 80%, 100% { transform: translateY(0); opacity: 0.4; }
    40%           { transform: translateY(-3px); opacity: 1; }
}
.aa-ai-chat-typing-dot {
    width: 6px;
    height: 6px;
    border-radius: 9999px;
    background-color: rgb(100 116 139); /* slate-500 */
    display: inline-block;
    animation: aa-ai-chat-dot-bounce 1s infinite ease-in-out both;
}
.aa-ai-chat-typing-dot:nth-child(1) { animation-delay: -0.32s; }
.aa-ai-chat-typing-dot:nth-child(2) { animation-delay: -0.16s; }
.aa-ai-chat-typing-dot:nth-child(3) { animation-delay: 0s; }

/* Thin scrollbar for history */
.aa-ai-chat-history::-webkit-scrollbar {
    width: 6px;
}
.aa-ai-chat-history::-webkit-scrollbar-track {
    background: transparent;
}
.aa-ai-chat-history::-webkit-scrollbar-thumb {
    background-color: rgb(203 213 225); /* slate-300 */
    border-radius: 9999px;
}
.aa-ai-chat-history {
    scrollbar-width: thin;
    scrollbar-color: rgb(203 213 225) transparent;
}

/* Autosize textarea: hard ceiling ~4 lines. line-height 20px * 4 + padding */
.aa-ai-chat-textarea {
    max-height: 96px;
}
</style>

<template id="aa-ai-chat-fab-template">
    <button
        type="button"
        id="aa-btn-open-aichat"
        class="inline-flex items-center gap-2 px-4 py-3 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 rounded-full shadow-lg shadow-indigo-600/25 hover:shadow-xl hover:shadow-indigo-600/30 transition-all duration-200 focus:outline-none focus:ring-4 focus:ring-indigo-500/30"
        aria-label="Abrir asistente IA"
        aria-haspopup="dialog"
        aria-expanded="false">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.847.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z"/>
        </svg>
        <span>Asistente IA</span>
    </button>
</template>

<template id="aa-ai-chat-panel-template">
    <div
        id="aa-ai-chat-panel"
        role="dialog"
        aria-labelledby="aa-ai-chat-title"
        aria-modal="false"
        class="hidden fixed bottom-24 right-6 z-50 max-w-[380px] w-[calc(100vw-2rem)] max-h-[600px] h-[calc(100vh-8rem)] flex-col bg-white rounded-2xl shadow-2xl shadow-slate-900/20 border border-slate-200 overflow-hidden">

        <!-- Header -->
        <header class="flex items-center justify-between gap-2 px-4 py-3 border-b border-slate-200 bg-white">
            <div class="flex items-center gap-2 min-w-0">
                <span class="flex items-center justify-center w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 shrink-0" aria-hidden="true">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.847.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z"/>
                    </svg>
                </span>
                <div class="min-w-0">
                    <h2 id="aa-ai-chat-title" class="flex items-center gap-1.5 min-w-0 text-sm font-semibold text-slate-900">
                        <span class="truncate">Asistente IA</span>
                        <span class="shrink-0 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium leading-none text-indigo-700 bg-indigo-50 border border-indigo-100" aria-hidden="true">Beta</span>
                    </h2>
                    <p class="text-xs text-slate-500 truncate">Pídeme agendar una cita</p>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <button
                    type="button"
                    id="aa-ai-chat-reset"
                    class="p-1.5 rounded-full text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                    aria-label="Nueva conversación">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                    </svg>
                </button>
                <button
                    type="button"
                    id="aa-ai-chat-close"
                    class="p-1.5 rounded-full text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                    aria-label="Cerrar asistente">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </header>

        <!-- History -->
        <div
            id="aa-ai-chat-history"
            class="aa-ai-chat-history flex-1 overflow-y-auto px-4 py-4 space-y-3 bg-slate-50/40"
            aria-live="polite"
            aria-atomic="false"
            role="log"></div>

        <!-- Composer -->
        <form id="aa-ai-chat-composer" class="border-t border-slate-200 bg-white p-3">
            <div class="flex items-end gap-2">
                <label for="aa-ai-chat-input" class="sr-only">Escribe tu mensaje</label>
                <textarea
                    id="aa-ai-chat-input"
                    maxlength="300"
                    rows="1"
                    placeholder="Escribe tu mensaje..."
                    class="aa-ai-chat-textarea flex-1 resize-none px-3 py-2 text-sm text-slate-800 placeholder:text-slate-400 bg-slate-100 border border-transparent rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-300 focus:bg-white transition-colors"></textarea>
                <button
                    type="submit"
                    id="aa-ai-chat-send"
                    class="flex items-center justify-center w-10 h-10 shrink-0 text-white bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-300 disabled:cursor-not-allowed rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                    aria-label="Enviar mensaje"
                    disabled>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0 1 21.485 12 59.77 59.77 0 0 1 3.27 20.876L5.999 12Zm0 0h7.5"/>
                    </svg>
                </button>
            </div>
        </form>
    </div>
</template>
