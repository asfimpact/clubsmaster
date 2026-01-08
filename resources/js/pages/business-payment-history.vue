<script setup>
import { ref, onMounted, computed } from 'vue'
import { useApi } from '@/composables/useApi'

definePage({
  // Empty - follows dashboard pattern, protected by global auth guard
})

const invoices = ref([])
const loading = ref(true)
const refreshing = ref(false)
const lastUpdated = ref(null)
const cached = ref(true)

const headers = [
  { title: 'Date', key: 'date', sortable: true },
  { title: 'Amount', key: 'amount', sortable: true },
  { title: 'Description', key: 'description' },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Receipt', key: 'pdf_url', sortable: false },
]

const timeAgo = computed(() => {
  if (!lastUpdated.value) return ''
  const seconds = Math.floor((new Date() - new Date(lastUpdated.value)) / 1000)
  if (seconds < 60) return 'just now'
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`
  return `${Math.floor(seconds / 3600)}h ago`
})

const fetchPaymentHistory = async (fresh = false) => {
  if (fresh) {
    refreshing.value = true
  } else {
    loading.value = true
  }
  
  try {
    const url = fresh ? '/user/payment-history?fresh=1' : '/user/payment-history'
    const { data, error, statusCode } = await useApi(url)
    
    // Check for authentication error
    if (statusCode.value === 401 || error.value) {
      window.location.href = '/login'
      return
    }
    
    invoices.value = data.value?.invoices || []
    lastUpdated.value = data.value?.last_updated || new Date().toISOString()
    cached.value = data.value?.cached !== false
  } catch (error) {
    console.error('Failed to fetch payment history:', error)
    // If error is auth-related, redirect to login
    if (error.message?.includes('401') || error.message?.includes('Unauthorized')) {
      window.location.href = '/login'
    }
  } finally {
    loading.value = false
    refreshing.value = false
  }
}

const refreshInvoices = () => {
  fetchPaymentHistory(true)
}

onMounted(() => {
  fetchPaymentHistory()
})
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard>
        <VCardTitle class="d-flex align-center justify-space-between">
          <span>Payment History</span>
          <div class="d-flex gap-2 align-center">
            <VChip v-if="lastUpdated" size="small" :color="cached ? 'info' : 'success'">
              <VIcon icon="tabler-clock" size="16" class="me-1" />
              {{ timeAgo }}
            </VChip>
            <VBtn
              size="small"
              variant="tonal"
              prepend-icon="tabler-refresh"
              :loading="refreshing"
              @click="refreshInvoices"
            >
              Refresh
            </VBtn>
          </div>
        </VCardTitle>
        <VCardText>
          <!-- Empty State -->
          <div v-if="!loading && invoices.length === 0" class="text-center pa-8">
            <VIcon size="64" color="grey-lighten-1">
              mdi-receipt-text-outline
            </VIcon>
            <h3 class="text-h5 mt-4 mb-2">
              No payments yet
            </h3>
            <p class="text-body-1 text-medium-emphasis mb-4">
              Start your journey by choosing a plan!
            </p>
            <VBtn to="/pages/pricing" color="primary">
              View Plans
            </VBtn>
          </div>

          <!-- Data Table -->
          <VDataTable
            v-else
            :items="invoices"
            :headers="headers"
            :loading="loading"
            :items-per-page="10"
            class="elevation-1"
          >
            <template #item.status="{ item }">
              <VChip
                :color="item.status === 'Paid' ? 'success' : 'warning'"
                size="small"
              >
                {{ item.status }}
              </VChip>
            </template>

            <template #item.pdf_url="{ item }">
              <VBtn
                :href="item.pdf_url"
                target="_blank"
                size="small"
                variant="tonal"
                prepend-icon="tabler-download"
              >
                View & Download
              </VBtn>
            </template>
          </VDataTable>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
