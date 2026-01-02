<script setup>
import safeBoxWithGoldenCoin from '@images/misc/3d-safe-box-with-golden-dollar-coins.png'
import spaceRocket from '@images/misc/3d-space-rocket-with-smoke.png'
import dollarCoinPiggyBank from '@images/misc/dollar-coins-flying-pink-piggy-bank.png'
import { useApi } from '@/composables/useApi'

const props = defineProps({
  title: {
    type: String,
    required: false,
  },
  xs: {
    type: [
      Number,
      String,
    ],
    required: false,
  },
  sm: {
    type: [
      Number,
      String,
    ],
    required: false,
  },
  md: {
    type: [
      String,
      Number,
    ],
    required: false,
  },
  lg: {
    type: [
      String,
      Number,
    ],
    required: false,
  },
  xl: {
    type: [
      String,
      Number,
    ],
    required: false,
  },
})

const annualMonthlyPlanPriceToggler = ref(true)

const pricingPlans = ref([])
const userData = useCookie('userData')
const activeSubscriptionId = ref(null)
const loadingPlanId = ref(null) // Track which plan is loading

// Free plan confirmation modal
const showFreePlanModal = ref(false)
const selectedFreePlan = ref(null)
const freePlanDuration = ref(0)

// Snackbar notification
const snackbar = ref(false)
const snackbarMessage = ref('')
const snackbarColor = ref('success')

// Check if user has ever used free trial
const hasUsedFreeTrial = computed(() => {
  const value = userData.value?.has_used_free_trial || false
  console.log('🔍 Free trial check:', { 
    userData: userData.value, 
    has_used_free_trial: userData.value?.has_used_free_trial,
    computed: value 
  })
  return value
})

const subscribeToPlan = async (planId) => {
    // Prevent double-clicks
    if (loadingPlanId.value) return
    
    try {
        // Set loading state
        loadingPlanId.value = planId
        const frequency = annualMonthlyPlanPriceToggler.value ? 'yearly' : 'monthly'
        const selectedPlan = pricingPlans.value.find(p => p.id === planId)
        
        // Check if this is a free plan (price = 0)
        const price = frequency === 'yearly' ? selectedPlan.yearlyPrice : selectedPlan.monthlyPrice
        const isFree = price == 0 || price === null || price === '0'
        
        console.log('Plan check:', { planId, frequency, price, isFree, selectedPlan })
        
        if (isFree) {
            // Free tier: Show confirmation modal
            selectedFreePlan.value = selectedPlan
            
            // Calculate duration based on frequency
            if (frequency === 'yearly') {
                freePlanDuration.value = selectedPlan.yearlyDurationDays || 365
            } else {
                freePlanDuration.value = selectedPlan.durationDays || 30
            }
            
            showFreePlanModal.value = true
            loadingPlanId.value = null
        } else {
            // Paid plan: Redirect to Stripe Checkout
            console.log('Calling Stripe checkout with:', {
                plan_id: planId,
                frequency: frequency
            })
            
            const { data, error } = await useApi('/stripe/checkout', {
                method: 'POST',
                body: {
                    plan_id: planId,
                    frequency: frequency
                }
            })
            
            console.log('Stripe checkout response:', { data: data.value, error: error.value })
            
            if (error.value) {
                console.error('Stripe checkout error:', error.value)
                alert('Failed to create checkout session. Please check console for details.')
                loadingPlanId.value = null
                return
            }
            
            // Check if this was a swap (existing subscriber upgrading/downgrading)
            if (data.value && data.value.swapped) {
                // Plan was swapped instantly - no redirect needed
                snackbarMessage.value = data.value.message || 'Your plan has been updated successfully!'
                snackbarColor.value = 'success'
                snackbar.value = true
                
                // Small delay to allow webhook to process and sync payment method
                setTimeout(async () => {
                    await fetchPlans()
                    loadingPlanId.value = null
                }, 3000) // 3 seconds gives webhook time to complete
            } else if (data.value && data.value.url) {
                // Redirect to Stripe Checkout (new subscriber OR fallback for missing payment method)
                if (data.value.fallback) {
                    console.log('Redirecting to Stripe Checkout (payment method required)')
                }
                window.location.href = data.value.url
            } else if (data.value && data.value.error) {
                alert(data.value.error)
                loadingPlanId.value = null
            } else {
                alert('Unexpected response from server.')
                loadingPlanId.value = null
            }
        }
    } catch (e) {
        console.error("Subscription failed", e)
        alert("Failed to subscribe. Please try again.")
        loadingPlanId.value = null
    }
}

// Confirm free plan subscription
const confirmFreePlan = async () => {
    if (!selectedFreePlan.value) return
    
    try {
        loadingPlanId.value = selectedFreePlan.value.id
        showFreePlanModal.value = false
        
        const frequency = annualMonthlyPlanPriceToggler.value ? 'yearly' : 'monthly'
        
        const { data, error } = await useApi('/user/subscribe', {
            method: 'POST',
            body: {
                plan_id: selectedFreePlan.value.id,
                frequency: frequency
            }
        })
        
        if (error.value) {
            alert('Failed to activate subscription. Please try again.')
            loadingPlanId.value = null
            return
        }
        
        if (data.value && data.value.user) {
            // Update local state
            userData.value = data.value.user
            activeSubscriptionId.value = data.value.user.subscription?.plan_id
            
            // Show success snackbar
            snackbarMessage.value = data.value.message
            snackbarColor.value = 'success'
            snackbar.value = true
            
            fetchPlans()
        }
        
        loadingPlanId.value = null
    } catch (e) {
        console.error("Free plan activation failed", e)
        alert("Failed to activate subscription. Please try again.")
        loadingPlanId.value = null
    }
}

const fetchPlans = async () => {
    try {
        // Fetch User first to get subscription & refresh global state
        const { data: userRes } = await useApi('/user')
        if (userRes.value) {
           userData.value = userRes.value // Sync global cookie
           activeSubscriptionId.value = userRes.value.subscription?.plan_id
           
           // Set toggle to match user's current subscription frequency
           if (userRes.value.current_subscription_frequency) {
               annualMonthlyPlanPriceToggler.value = userRes.value.current_subscription_frequency === 'yearly'
           }
        }

        const { data } = await useApi('/user/plans')
        if (data.value) {
             pricingPlans.value = data.value.map((plan, index) => {
                let logo = dollarCoinPiggyBank
                if (index % 3 === 1) logo = safeBoxWithGoldenCoin
                if (index % 3 === 2) logo = spaceRocket

                return {
                    id: plan.id, // Ensure ID is passed
                    name: plan.name,
                    tagLine: plan.tagline || 'A simple start for everyone',
                    logo: logo,
                    monthlyPrice: plan.price,
                    yearlyPrice: plan.yearly_price ? parseFloat(plan.yearly_price) : (parseFloat(plan.price) * 12),
                    durationDays: plan.duration_days || 30,
                    yearlyDurationDays: plan.yearly_duration_days || 365,
                    isPopular: false,
                    // Check against active sub - must match both plan ID and frequency
                    current: activeSubscriptionId.value === plan.id && 
                             userData.value?.current_subscription_frequency === (annualMonthlyPlanPriceToggler.value ? 'yearly' : 'monthly'),
                    features: Array.isArray(plan.features) ? plan.features : []
                }
             })
        }
    } catch (e) {
        console.error("Failed to load plans", e)
    }
}

// Helper function to check if a plan is the user's current plan
// This needs to be a function, not a stored value, so it recalculates when toggle changes
const isPlanCurrent = (planId) => {
    const currentToggleFreq = annualMonthlyPlanPriceToggler.value ? 'yearly' : 'monthly'
    return activeSubscriptionId.value === planId && 
           userData.value?.current_subscription_frequency === currentToggleFreq
}

// Fetch fresh user data
const fetchUser = async () => {
  try {
    const { data } = await useApi('/user')
    if (data.value) {
      userData.value = data.value
      console.log('✅ User data updated:', {
        has_used_free_trial: data.value.has_used_free_trial,
        userData: userData.value
      })
    }
  } catch (e) {
    console.error('Failed to fetch user data', e)
  }
}

onMounted(() => {
    fetchUser()  // Fetch fresh user data first
    fetchPlans()
})
</script>

<template>
  <!-- 👉 Title and subtitle -->
  <div class="text-center">
    <h3 class="text-h3 pricing-title mb-2">
      {{ props.title ? props.title : 'Pricing Plans' }}
    </h3>
    <p class="mb-0">
      All plans include 40+ advanced tools and features to boost your product.
    </p>
    <p class="mb-2">
      Choose the best plan to fit your needs.
    </p>
  </div>

  <!-- 👉 Annual and monthly price toggler -->

  <div class="d-flex font-weight-medium text-body-1 align-center justify-center mx-auto mt-12 mb-6">
    <VLabel
      for="pricing-plan-toggle"
      class="me-3"
    >
      Monthly
    </VLabel>

    <div class="position-relative">
      <VSwitch
        id="pricing-plan-toggle"
        v-model="annualMonthlyPlanPriceToggler"
      >
        <template #label>
          <div class="text-body-1 font-weight-medium">
            Annually
          </div>
        </template>
      </VSwitch>

      <div class="save-upto-chip position-absolute align-center d-none d-md-flex gap-1">
        <VIcon
          icon="tabler-corner-left-down"
          size="24"
          class="flip-in-rtl mt-2 text-disabled"
        />
        <VChip
          label
          color="primary"
          size="small"
        >
          Save up to 30%
        </VChip>
      </div>
    </div>
  </div>

  <!-- SECTION pricing plans -->
  <VRow>
    <VCol
      v-for="plan in pricingPlans"
      :key="plan.logo"
      v-bind="props"
      cols="12"
    >
      <!-- 👉  Card -->
      <VCard
        flat
        border
        :class="plan.isPopular ? 'border-primary border-opacity-100' : ''"
      >
        <VCardText
          style="block-size: 3.75rem;"
          class="text-end"
        >
          <!-- 👉 Popular -->
          <VChip
            v-show="plan.isPopular"
            label
            color="primary"
            size="small"
          >
            Popular
          </VChip>
        </VCardText>

        <!-- 👉 Plan logo -->
        <VCardText>
          <VImg
            :height="120"
            :width="120"
            :src="plan.logo"
            class="mx-auto mb-5"
          />

          <!-- 👉 Plan name -->
          <h4 class="text-h4 mb-1 text-center">
            {{ plan.name }}
          </h4>
          <p class="mb-0 text-body-1 text-center">
            {{ plan.tagLine }}
          </p>

          <!-- 👉 Plan price  -->

          <div class="position-relative">
            <div class="d-flex justify-center pt-5 pb-10">
              <div class="text-body-1 align-self-start font-weight-medium">
                £
              </div>
              <h1 class="text-h1 font-weight-medium text-primary">
                {{ annualMonthlyPlanPriceToggler ? plan.yearlyPrice : plan.monthlyPrice }}
              </h1>
              <div class="text-body-1 font-weight-medium align-self-end">
                {{ annualMonthlyPlanPriceToggler ? '/year' : '/month' }}
              </div>
            </div>

            <!-- 👉 Annual Price -->
            <!-- <span
              v-show="annualMonthlyPlanPriceToggler"
              class="annual-price-text position-absolute text-caption text-disabled pb-4"
            >
              {{ plan.yearlyPrice === 0 ? 'free' : `GBP ${plan.yearlyPrice}/Year` }}
            </span> -->
          </div>

          <!-- 👉 Plan features -->

          <VList class="card-list mb-4">
            <VListItem
              v-for="feature in plan.features"
              :key="feature"
            >
              <template #prepend>
                <VIcon
                  size="8"
                  icon="tabler-circle-filled"
                  color="rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity))"
                />
              </template>

              <VListItemTitle class="text-body-1">
                {{ feature }}
              </VListItemTitle>
            </VListItem>
          </VList>

          <!-- 👉 Plan actions -->
          <VBtn
            block
            :color="isPlanCurrent(plan.id) ? 'success' : (plan.monthlyPrice == 0 && hasUsedFreeTrial ? 'grey' : 'primary')"
            :variant="plan.isPopular ? 'elevated' : 'tonal'"
            :active="false"
            :disabled="isPlanCurrent(plan.id) || loadingPlanId === plan.id || (plan.monthlyPrice == 0 && hasUsedFreeTrial)"
            :loading="loadingPlanId === plan.id"
            @click="!isPlanCurrent(plan.id) && subscribeToPlan(plan.id)"
          >
            <template v-if="loadingPlanId === plan.id">
              Redirecting to Checkout...
            </template>
            <template v-else-if="plan.monthlyPrice == 0 && hasUsedFreeTrial">
              Trial Used - Upgrade Required
            </template>
            <template v-else>
              {{ isPlanCurrent(plan.id) ? 'Your Current Plan' : 'Select Plan' }}
            </template>
          </VBtn>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
  <!-- !SECTION  -->

  <!-- Free Plan Confirmation Modal -->
  <VDialog
    v-model="showFreePlanModal"
    max-width="500"
  >
    <VCard>
      <VCardTitle class="text-h5">
        Activate {{ selectedFreePlan?.name }}?
      </VCardTitle>

      <VCardText>
        <p class="mb-4">
          You will have access to the <strong>{{ selectedFreePlan?.name }}</strong> plan for <strong>{{ freePlanDuration }} days</strong>.
        </p>
        <p class="text-body-2 text-medium-emphasis">
          This is a free plan with no payment required. Click confirm to activate your subscription.
        </p>
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn
          color="secondary"
          variant="outlined"
          @click="showFreePlanModal = false"
        >
          Cancel
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
          @click="confirmFreePlan"
        >
          Confirm & Activate
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Success Snackbar -->
  <VSnackbar
    v-model="snackbar"
    :color="snackbarColor"
    location="top end"
    :timeout="5000"
  >
    {{ snackbarMessage }}
  </VSnackbar>
</template>

<style lang="scss" scoped>
.card-list {
  --v-card-list-gap: 1rem;
}

.save-upto-chip {
  inset-block-start: -2.4rem;
  inset-inline-end: -6rem;
}

.annual-price-text {
  inset-block-end: 3%;
  inset-inline-start: 50%;
  transform: translateX(-50%);
}
</style>
