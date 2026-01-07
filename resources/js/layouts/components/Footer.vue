<template>
  <div class="h-100 d-flex align-center justify-md-space-between justify-center">
    <!-- 👉 Footer: left content -->
    <span class="d-flex align-center text-medium-emphasis">
      &copy;
      {{ new Date().getFullYear() }}
      Made with
      <VIcon
        icon="tabler-heart-filled"
        color="error"
        size="1.25rem"
        class="mx-1"
      />
      by <span class="text-primary ms-1">Sanainfotech Ltd</span>
    </span>
    <!-- 👉 Footer: right content -->
    <span class="d-md-flex gap-x-4 text-primary d-none">
      <a href="#" @click.prevent>Privacy Policy</a>
      <a href="#" @click.prevent>Terms of Use</a>
      <a href="#" @click.prevent>Support</a>
    </span>
  </div>
</template>
