<!-- ResponsiveTable.vue — ResponsiveTable component.

  exports: none
  used_by: none
  rules:   none
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
-->

<template>
    <div class="hidden overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm md:block">
        <table class="w-full text-left text-sm">
            <slot name="table" />
        </table>
    </div>
    <div class="space-y-3 md:hidden">
        <slot name="cards" />
    </div>
</template>
