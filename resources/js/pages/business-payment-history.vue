<script setup>
import { ref, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'

definePage({
  // Empty - follows dashboard pattern, protected by global auth guard
})

const invoices = ref([])
const loading = ref(true)

const headers = [
  { title: 'Date', key: 'date', sortable: true },
  { title: 'Amount', key: 'amount', sortable: true },
  { title: 'Description', key: 'description' },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Receipt', key: 'pdf_url', sortable: false },
]

const fetchPaymentHistory = async () => {
  loading.value = true
  try {
    const { data, error, statusCode } = await useApi('/user/payment-history')
    
    // Check for authentication error
    if (statusCode.value === 401 || error.value) {
      window.location.href = '/login'
      return
    }
    
    invoices.value = data.value?.invoices || []
  } catch (error) {
    console.error('Failed to fetch payment history:', error)
    // If error is auth-related, redirect to login
    if (error.message?.includes('401') || error.message?.includes('Unauthorized')) {
      window.location.href = '/login'
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchPaymentHistory()
})
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard title="Payment History">
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
