<script setup>
import ConfirmDialog from '@/components/dialogs/ConfirmDialog.vue'

definePage({
  name: 'admin-pricing-mgmt',
  meta: {
    action: 'manage',
    subject: 'Admin',
  },
})

// 👉 States
const plans = ref([])
const loading = ref(false)
const isPlanDialogVisible = ref(false)
const isConfirmDialogVisible = ref(false)
const selectedPlan = ref(null)

const planForm = ref({
  id: null,
  name: '',
  price: 0,
  duration_days: 30,
})

const headers = [
  { title: 'ID', key: 'id' },
  { title: 'PLAN NAME', key: 'name' },
  { title: 'PRICE', key: 'price' },
  { title: 'DURATION (DAYS)', key: 'duration_days' },
  { title: 'CREATED', key: 'created_at' },
  { title: 'ACTIONS', key: 'actions', sortable: false },
]

// 👉 Methods
const fetchPlans = async () => {
  loading.value = true
  try {
    const response = await $api('/admin/plans')
    plans.value = response
  } catch (error) {
    console.error('Error fetching plans:', error)
  } finally {
    loading.value = false
  }
}

const openPlanDialog = (plan = null) => {
  if (plan) {
    planForm.value = { ...plan }
  } else {
    planForm.value = { id: null, name: '', price: 0, duration_days: 30 }
  }
  isPlanDialogVisible.value = true
}

const savePlan = async () => {
  try {
    const method = planForm.value.id ? 'PUT' : 'POST'
    const url = planForm.value.id ? `/admin/plans/${planForm.value.id}` : '/admin/plans'
    
    await $api(url, {
      method,
      body: planForm.value,
    })
    
    isPlanDialogVisible.value = false
    fetchPlans()
  } catch (error) {
    console.error('Error saving plan:', error)
  }
}

const deletePlan = async (confirmed) => {
  if (!confirmed || !selectedPlan.value) return
  
  try {
    await $api(`/admin/plans/${selectedPlan.value.id}`, { method: 'DELETE' })
    fetchPlans()
  } catch (error) {
    // Handle error (e.g., plan has subscriptions)
    console.error('Error deleting plan:', error)
  }
}

const confirmDelete = (plan) => {
  selectedPlan.value = plan
  isConfirmDialogVisible.value = true
}

onMounted(fetchPlans)
</script>

<template>
  <section>
    <VCard title="Pricing & Packages">
      <template #append>
        <VBtn
          prepend-icon="tabler-plus"
          @click="openPlanDialog()"
        >
          Add New Plan
        </VBtn>
      </template>

      <VCardText>
        <VDataTable
          :headers="headers"
          :items="plans"
          :loading="loading"
          class="text-no-wrap"
        >
          <!-- Price -->
          <template #item.price="{ item }">
            <span class="text-h6 font-weight-bold">${{ item.price }}</span>
          </template>

          <!-- Created At -->
          <template #item.created_at="{ item }">
            {{ new Date(item.created_at).toLocaleDateString() }}
          </template>

          <!-- Actions -->
          <template #item.actions="{ item }">
            <IconBtn @click="openPlanDialog(item)">
              <VIcon icon="tabler-pencil" />
            </IconBtn>
            <IconBtn color="error" @click="confirmDelete(item)">
              <VIcon icon="tabler-trash" />
            </IconBtn>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>

    <!-- 👉 Add/Edit Plan Dialog -->
    <VDialog
      v-model="isPlanDialogVisible"
      max-width="600"
    >
      <VCard :title="planForm.id ? 'Edit Plan' : 'Add New Plan'">
        <VCardText>
          <VRow>
            <VCol cols="12">
              <AppTextField
                v-model="planForm.name"
                label="Plan Name"
                placeholder="e.g. Pro Package"
              />
            </VCol>
            <VCol cols="12" md="6">
              <AppTextField
                v-model="planForm.price"
                label="Price ($)"
                type="number"
                prefix="$"
              />
            </VCol>
            <VCol cols="12" md="6">
              <AppTextField
                v-model="planForm.duration_days"
                label="Duration (Days)"
                type="number"
              />
            </VCol>
          </VRow>
        </VCardText>

        <VCardText class="d-flex justify-end gap-3 flex-wrap">
          <VBtn
            color="secondary"
            variant="tonal"
            @click="isPlanDialogVisible = false"
          >
            Cancel
          </VBtn>
          <VBtn @click="savePlan">
            {{ planForm.id ? 'Update' : 'Create' }} Plan
          </VBtn>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- 👉 Confirm Delete Dialog -->
    <ConfirmDialog
      v-model:is-dialog-visible="isConfirmDialogVisible"
      confirmation-question="Are you sure you want to delete this plan? This action cannot be undone if no users are assigned."
      confirm-title="Plan Deleted"
      confirm-msg="The membership plan has been removed."
      cancel-title="Cancelled"
      cancel-msg="No changes were made."
      @confirm="deletePlan"
    />
  </section>
</template>
