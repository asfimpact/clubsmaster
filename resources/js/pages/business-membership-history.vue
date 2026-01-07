<script setup>
import { ref, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'

definePage({
  // Empty - follows dashboard pattern, protected by global auth guard
})

const memberships = ref([])
const loading = ref(true)

const headers = [
  { title: 'Plan', key: 'plan_name', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Started', key: 'started_at', sortable: true },
  { title: 'Ends', key: 'ends_at', sortable: true },
]

const fetchMembershipHistory = async () => {
  loading.value = true
  try {
    const { data, error, statusCode } = await useApi('/user/membership-history')
    
    // Check for authentication error
    if (statusCode.value === 401 || error.value) {
      window.location.href = '/login'
      return
    }
    
    memberships.value = data.value?.memberships || []
  } catch (error) {
    console.error('Failed to fetch membership history:', error)
    // If error is auth-related, redirect to login
    if (error.message?.includes('401') || error.message?.includes('Unauthorized')) {
      window.location.href = '/login'
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchMembershipHistory()
})
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard title="Membership History">
        <VCardText>
          <!-- Empty State -->
          <div v-if="!loading && memberships.length === 0" class="text-center pa-8">
            <VIcon size="64" color="grey-lighten-1">
              mdi-history
            </VIcon>
            <h3 class="text-h5 mt-4 mb-2">
              No membership history
            </h3>
            <p class="text-body-1 text-medium-emphasis mb-4">
              Your subscription timeline will appear here once you join a plan.
            </p>
            <VBtn to="/pages/pricing" color="primary">
              Choose a Plan
            </VBtn>
          </div>

          <!-- Data Table -->
          <VDataTable
            v-else
            :items="memberships"
            :headers="headers"
            :loading="loading"
            :items-per-page="10"
            class="elevation-1"
          >
            <template #item.status="{ item }">
              <VChip
                :color="item.status_color"
                size="small"
              >
                {{ item.status_text }}
              </VChip>
            </template>

            <template #item.ends_at="{ item }">
              <span v-if="item.ends_at">{{ item.ends_at }}</span>
              <VChip v-else color="success" size="small" variant="tonal">
                Active
              </VChip>
            </template>
          </VDataTable>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
