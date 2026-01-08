<script setup>
import { useApi } from '@/composables/useApi'

const userData = useCookie('userData')
const router = useRouter()

definePage({
  name: 'index',
})

// 1. If Inactive (needs 2FA) or Pending (New User Email Verify), redirect to verification
if (userData.value?.status === 'Inactive' || userData.value?.status === 'Pending') {
  router.replace({ name: 'pages-authentication-two-steps-v1' })
}

// 2. Redirect Admins to CRM Dashboard
if (userData.value?.role === 'admin') {
  router.replace({ name: 'dashboards-crm' })
}

const dashboardData = computed(() => [
  {
    title: 'Current Plan',
    value: userData.value?.subscription_summary?.plan_name || 'No Active Plan',
    icon: 'tabler-award',
    color: 'primary',
  },
  {
    title: 'Status',
    value: userData.value?.access_control?.status 
      ? userData.value.access_control.status.charAt(0).toUpperCase() + userData.value.access_control.status.slice(1)
      : 'Inactive',
    icon: userData.value?.access_control?.can_access ? 'tabler-circle-check' : 'tabler-alert-circle',
    color: userData.value?.access_control?.can_access ? 'success' : 'warning',
  },
  {
    title: 'Next Billing / Expiry',
    value: userData.value?.subscription_summary?.expiry_date || 'N/A',
    icon: 'tabler-calendar-event',
    color: 'info',
  },
])

// Fetch fresh user data on mount and poll for updates
onMounted(async () => {
  // Check if returning from Stripe checkout
  const urlParams = new URLSearchParams(window.location.search)
  const paymentStatus = urlParams.get('payment')
  
  if (paymentStatus === 'success') {
    // Clear URL parameter
    window.history.replaceState({}, document.title, window.location.pathname)
    
    // Force refresh user data (clears cache on backend)
    try {
      const { data } = await useApi('/user?fresh=1')
      if (data.value) {
        userData.value = data.value
      }
    } catch (e) {
      console.error('Failed to refresh user data after checkout', e)
    }
  }

  // Regular user data refresh
  try {
    const { data } = await useApi('/user')
    if (data.value) {
      userData.value = data.value
    }
  } catch (e) {
    console.error('Failed to fetch user data on dashboard mount', e)
  }

  // Poll every 4 seconds to catch subscription changes from pricing component
  // (needed because useCookie refs don't share reactivity across components)
  setInterval(async () => {
    try {
      const { data } = await useApi('/user')
      if (data.value) {
        userData.value = data.value
      }
    } catch (e) {
      console.error('Failed to refresh user data', e)
    }
  }, 4000)
})

</script>

<template>
  <div>
    <VRow class="match-height">
      <!-- 👉 Welcome Card -->
      <VCol cols="12">
        <VCard>
          <VCardText>
            <h4 class="text-h4 mb-1">
              Welcome back, <span class="text-capitalize">{{ userData?.first_name || 'Client' }}</span>! 👋🏻
            </h4>
            <p class="mb-0">
              <template v-if="userData?.access_control?.can_access">
                <VIcon icon="tabler-circle-check" color="success" size="18" class="me-1" />
                Your subscription is <strong>active</strong>. Manage your membership below.
              </template>
              <template v-else>
                <VIcon icon="tabler-alert-circle" color="warning" size="18" class="me-1" />
                You don't have an active plan. <strong>Select a plan below</strong> to get started.
              </template>
            </p>
          </VCardText>
        </VCard>
      </VCol>

      <!-- 👉 Stats Cards -->
      <VCol
        v-for="data in dashboardData"
        :key="data.title"
        cols="12"
        md="4"
        sm="6"
      >
        <VCard>
          <VCardText class="d-flex align-center">
            <VAvatar
              variant="tonal"
              :color="data.color"
              rounded
              size="42"
              class="me-3"
            >
              <VIcon
                :icon="data.icon"
                size="26"
              />
            </VAvatar>

            <div class="d-flex flex-column">
              <span class="text-h6 font-weight-medium">{{ data.value }}</span>
              <span class="text-body-2">{{ data.title }}</span>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- 👉 Pricing Section -->
      <VCol cols="12">
        <VCard title="Upgrade Your Plan" style="min-height: 600px;">
          <VCardText>
            <AppPricing md="4" />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
