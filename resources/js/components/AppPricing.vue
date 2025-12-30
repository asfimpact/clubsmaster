<script setup>
import safeBoxWithGoldenCoin from '@images/misc/3d-safe-box-with-golden-dollar-coins.png'
import spaceRocket from '@images/misc/3d-space-rocket-with-smoke.png'
import dollarCoinPiggyBank from '@images/misc/dollar-coins-flying-pink-piggy-bank.png'

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

const subscribeToPlan = async (planId) => {
    try {
        const frequency = annualMonthlyPlanPriceToggler.value ? 'yearly' : 'monthly'
        const { data } = await useApi('/user/subscribe', {
            method: 'POST',
            body: {
                plan_id: planId,
                frequency: frequency
            }
        })
        
        if (data.value && data.value.user) {
            // Update local state
            userData.value = data.value.user
            activeSubscriptionId.value = data.value.user.subscription?.plan_id
            
            // Refresh plans to update UI badges
            // Actually just updating the ref is enough if we make computed
            // But for now, simple reload or alert?
            alert(data.value.message)
            // Re-fetch plans to update badges
            fetchPlans()
        }
    } catch (e) {
        console.error("Subscription failed", e)
        alert("Failed to subscribe")
    }
}

const fetchPlans = async () => {
    try {
        // Fetch User first to get subscription
        const { data: userRes } = await useApi('/user')
        if (userRes.value) {
           activeSubscriptionId.value = userRes.value.subscription?.plan_id // Assuming backend sends subscription or we load it
           // If backend /user doesn't send sub, we might need /user/billing logic
           // For now assuming we can get it. If not, we might need to update AuthController to include it.
           // Actually, earlier update to SubscriptionController returns loaded subscription.
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
                    isPopular: false,
                    // Check against active sub
                    current: activeSubscriptionId.value === plan.id, 
                    features: Array.isArray(plan.features) ? plan.features : []
                }
             })
        }
    } catch (e) {
        console.error("Failed to load plans", e)
    }
}

onMounted(() => {
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
          Save up to 10%
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
            :color="plan.current ? 'success' : 'primary'"
            :variant="plan.isPopular ? 'elevated' : 'tonal'"
            :active="false"
            :disabled="plan.current"
            @click="!plan.current && subscribeToPlan(plan.id)"
          >
            {{ plan.current ? 'Your Current Plan' : 'Select Plan' }}
          </VBtn>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
  <!-- !SECTION  -->
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
