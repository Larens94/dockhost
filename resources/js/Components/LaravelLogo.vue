<!-- LaravelLogo.vue — LaravelLogo component.

  exports: defineProps
  used_by: none
  rules:   none
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
-->

<script setup>
defineProps({
    size: {
        type: String,
        default: 'md',
    },
});
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center justify-center rounded-lg bg-[#FF2D20] text-white"
        :class="size === 'sm' ? 'h-7 w-7' : 'h-10 w-10'"
        aria-hidden="true"
    >
        <svg
            viewBox="0 0 24 24"
            :class="size === 'sm' ? 'h-4 w-4' : 'h-5 w-5'"
            fill="currentColor"
            xmlns="http://www.w3.org/2000/svg"
        >
            <path
                d="M23.642 5.43a.364.364 0 01.014.1v5.149c0 .135-.073.26-.189.326l-4.323 2.49v4.934a.378.378 0 01-.188.326L9.93 23.973a.378.378 0 01-.377 0L.533 18.755a.378.378 0 01-.188-.326V2.974c0-.033.005-.066.014-.098a.378.378 0 01.014-.019L5.046.285a.378.378 0 01.378 0l4.322 2.49 4.323-2.49a.377.377 0 01.377 0l4.519 2.605a.378.378 0 01.014.117zm-5.011 7.148l3.56-2.05-3.56-2.05-3.56 2.05 3.56 2.05zm-4.323 2.494v4.115l3.56-2.051v-4.115l-3.56 2.051zm-4.324-8.229l3.56-2.05-3.56-2.05-3.56 2.05 3.56 2.05zm0 1.007l-3.56 2.05 3.56 2.05 3.56-2.05-3.56-2.05zm-4.324-3.507l3.56 2.05v4.115l-3.56-2.05V4.343zm-.376 10.964l3.56 2.051v-4.115l-3.56-2.05v4.114zm7.627-3.537l-3.56 2.05 3.56 2.052 3.56-2.052-3.56-2.05z"
            />
        </svg>
    </span>
</template>
