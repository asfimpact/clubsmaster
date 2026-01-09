<script setup>
const props = defineProps({
  show: {
    type: Boolean,
    required: true,
  },
  message: {
    type: String,
    default: 'Processing...',
  },
  progress: {
    type: Number,
    default: 0,
  },
})
</script>

<template>
  <VOverlay
    :model-value="show"
    persistent
    class="align-center justify-center"
  >
    <VCard
      max-width="400"
      class="text-center pa-6"
    >
      <VIcon
        icon="tabler-circle-check"
        size="64"
        color="success"
        class="mb-4"
      />
      
      <h3 class="text-h5 mb-2">
        {{ message }}
      </h3>
      
      <p class="text-body-2 mb-4">
        Please wait while we process your request
      </p>
      
      <VProgressLinear
        :model-value="progress"
        color="success"
        height="6"
        rounded
        class="mb-2"
      />
      
      <p class="text-caption text-medium-emphasis">
        This usually takes 2-5 seconds
      </p>
    </VCard>
  </VOverlay>
</template>
