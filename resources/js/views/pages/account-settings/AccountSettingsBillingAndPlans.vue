<script setup>
import BillingHistoryTable from './BillingHistoryTable.vue'
import mastercard from '@images/icons/payments/mastercard.png'
import visa from '@images/icons/payments/visa.png'
import { loadStripe } from '@stripe/stripe-js'

const selectedPaymentMethod = ref('credit-debit-atm-card')
const isPricingPlanDialogVisible = ref(false)
const isConfirmDialogVisible = ref(false)
const isAddCardDialogVisible = ref(false)
const isDeleteCardDialogVisible = ref(false)
const cardToDelete = ref(null)

// Snackbar
const snackbar = ref(false)
const snackbarMessage = ref('')
const snackbarColor = ref('success')

// Loading States
const isLoadingPaymentMethods = ref(true)
const isLoadingBillingAddress = ref(true)

// Error States
const paymentMethodsError = ref(false)
const billingAddressError = ref(false)

// API Data
const paymentMethods = ref([])
const billingAddress = ref({
  line1: '',
  line2: '',
  city: '',
  state: '',
  postal_code: '',
  country: 'GB',
})

// Stripe
const stripeInstance = ref(null)
const cardElement = ref(null)
const clientSecret = ref(null)
const loadingCard = ref(false)

const countryList = [
  { value: 'GB', title: 'United Kingdom' },
  { value: 'US', title: 'United States' },
  { value: 'CA', title: 'Canada' },
  { value: 'AU', title: 'Australia' },
  { value: 'NZ', title: 'New Zealand' },
  { value: 'IN', title: 'India' },
  { value: 'RU', title: 'Russia' },
  { value: 'CN', title: 'China' },
  { value: 'JP', title: 'Japan' },
]

// Existing plan details fetching
const planDetails = ref({
    plan_name: 'Loading...',
    plan_price: 0,
    status: 'inactive',
    active_until: 'N/A',
    days_consumed: 0,
    total_days: 0,
    days_remaining: 0,
    progress_percent: 0,
    currency: '$', 
})

const fetchBilling = async () => {
    try {
        const { data } = await useApi('/user/billing')
        if (data.value) {
            planDetails.value = data.value
        }
    } catch (e) {
        console.error("Failed to fetch billing", e)
    }
}

// Fetch payment methods
const fetchPaymentMethods = async () => {
  isLoadingPaymentMethods.value = true
  paymentMethodsError.value = false
  
  try {
    const { data } = await useApi('/payment-methods')
    if (data.value && data.value.payment_methods) {
      paymentMethods.value = data.value.payment_methods
    }
  } catch (e) {
    console.error('Failed to fetch payment methods', e)
    paymentMethodsError.value = true
  } finally {
    isLoadingPaymentMethods.value = false
  }
}

// Fetch billing address
const fetchBillingAddress = async () => {
  isLoadingBillingAddress.value = true
  billingAddressError.value = false
  
  try {
    const { data } = await useApi('/billing-address')
    if (data.value && data.value.address) {
      billingAddress.value = data.value.address
    }
  } catch (e) {
    console.error('Failed to fetch billing address', e)
    billingAddressError.value = true
  } finally {
    isLoadingBillingAddress.value = false
  }
}

// Set default payment method
const setDefaultPaymentMethod = async (pmId) => {
  try {
    const { data } = await useApi(`/payment-methods/${pmId}/set-default`, { method: 'POST' })
    if (data.value && data.value.success) {
      await fetchPaymentMethods()
      
      // Show success snackbar
      snackbarMessage.value = 'Payment method set as default'
      snackbarColor.value = 'success'
      snackbar.value = true
    }
  } catch (e) {
    snackbarMessage.value = 'Failed to set default payment method'
    snackbarColor.value = 'error'
    snackbar.value = true
    console.error('Failed to set default payment method', e)
  }
}

// Delete payment method
const openDeleteCardDialog = (pmId) => {
  cardToDelete.value = pmId
  isDeleteCardDialogVisible.value = true
}

const deletePaymentMethod = async () => {
  const pmId = cardToDelete.value
  if (!pmId) return
  
  isDeleteCardDialogVisible.value = false
  
  try {
    const token = useCookie('accessToken').value
    const response = await fetch(`/api/payment-methods/${pmId}`, {
      method: 'DELETE',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    })
    
    const data = await response.json()
    
    console.log('Delete response:', { status: response.status, data })
    
    if (!response.ok) {
      // Error response
      const errorMsg = data.error || data.message || 'Failed to delete payment method'
      snackbarMessage.value = errorMsg
      snackbarColor.value = 'error'
      snackbar.value = true
      return
    }
    
    // Success
    await fetchPaymentMethods()
    
    snackbarMessage.value = 'Payment method deleted successfully'
    snackbarColor.value = 'success'
    snackbar.value = true
  } catch (e) {
    console.error('Delete error caught:', e)
    
    snackbarMessage.value = 'Failed to delete payment method'
    snackbarColor.value = 'error'
    snackbar.value = true
  }
}

// Open add card dialog
const openAddCardDialog = async () => {
  isAddCardDialogVisible.value = true
  
  // Get SetupIntent client secret
  try {
    const { data } = await useApi('/payment-methods/setup-intent', { method: 'POST' })
    if (data.value && data.value.client_secret) {
      clientSecret.value = data.value.client_secret
      
      // Initialize Stripe Elements after dialog opens
      await nextTick()
      await initializeStripeElements()
    }
  } catch (e) {
    console.error('Failed to create SetupIntent', e)
    snackbarMessage.value = 'Failed to initialize payment form'
    snackbarColor.value = 'error'
    snackbar.value = true
    isAddCardDialogVisible.value = false
  }
}

// Initialize Stripe Elements
const initializeStripeElements = async () => {
  try {
    // Load Stripe (use your publishable key)
    const stripe = await loadStripe(import.meta.env.VITE_STRIPE_KEY || 'pk_test_...')
    stripeInstance.value = stripe
    
    const elements = stripe.elements()
    const card = elements.create('card', {
      hidePostalCode: true, // Hide postal code - we'll collect it in billing address form
      style: {
        base: {
          fontSize: '16px',
          color: '#424770',
          '::placeholder': {
            color: '#aab7c4',
          },
        },
        invalid: {
          color: '#9e2146',
        },
      },
    })
    
    await nextTick()
    const cardElementDiv = document.getElementById('card-element')
    if (cardElementDiv) {
      card.mount('#card-element')
      cardElement.value = card
    }
  } catch (e) {
    console.error('Failed to initialize Stripe Elements', e)
  }
}

// Submit new card
const submitNewCard = async () => {
  if (!stripeInstance.value || !cardElement.value || !clientSecret.value) return
  
  loadingCard.value = true
  
  try {
    const { setupIntent, error } = await stripeInstance.value.confirmCardSetup(clientSecret.value, {
      payment_method: {
        card: cardElement.value,
      },
    })
    
    if (error) {
      snackbarMessage.value = error.message
      snackbarColor.value = 'error'
      snackbar.value = true
    } else if (setupIntent.status === 'succeeded') {
      // Success! Close dialog and refresh payment methods
      isAddCardDialogVisible.value = false
      await fetchPaymentMethods()
      
      snackbarMessage.value = 'Card added successfully!'
      snackbarColor.value = 'success'
      snackbar.value = true
    }
  } catch (e) {
    console.error('Failed to add card', e)
    snackbarMessage.value = 'Failed to add card'
    snackbarColor.value = 'error'
    snackbar.value = true
  } finally {
    loadingCard.value = false
  }
}

// Save billing address
const saveBillingAddress = async () => {
  try {
    const { data } = await useApi('/billing-address', {
      method: 'POST',
      body: billingAddress.value,
    })
    
    if (data.value && data.value.address) {
      billingAddress.value = data.value.address
      
      snackbarMessage.value = 'Billing address saved successfully!'
      snackbarColor.value = 'success'
      snackbar.value = true
    }
  } catch (e) {
    console.error('Failed to save billing address', e)
    snackbarMessage.value = 'Failed to save billing address'
    snackbarColor.value = 'error'
    snackbar.value = true
  }
}

// Get card brand image
const getCardImage = (brand) => {
  const brandMap = {
    visa: visa,
    mastercard: mastercard,
    amex: mastercard, // fallback
    discover: mastercard, // fallback
  }
  return brandMap[brand?.toLowerCase()] || mastercard
}

onMounted(() => {
    fetchBilling()
    fetchPaymentMethods()
    fetchBillingAddress()
})
</script>

<template>
  <VRow>
    <!-- 👉 Current Plan -->
    <VCol cols="12">
      <VCard title="Current Plan">
        <VCardText>
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <div>
                <div class="mb-6">
                  <h3 class="text-body-1 text-high-emphasis font-weight-medium mb-1">
                    Your Current Plan is {{ planDetails.plan_name }}
                  </h3>
                  <p class="text-body-1">
                    {{ planDetails.status === 'active' ? 'Enjoy your premium features' : 'Not Active' }}
                  </p>
                </div>

                <div class="mb-6">
                  <h3 class="text-body-1 text-high-emphasis font-weight-medium mb-1">
                    Active until {{ planDetails.active_until }}
                  </h3>
                  <p class="text-body-1">
                    We will send you a notification upon Subscription expiration
                  </p>
                </div>

                <div>
                  <h3 class="text-body-1 text-high-emphasis font-weight-medium mb-1">
                    <span class="me-2">{{ planDetails.currency }}{{ planDetails.plan_price }} Per Month</span>
                    <VChip
                      v-if="planDetails.status === 'active'"
                      color="primary"
                      size="small"
                      label
                    >
                      Active
                    </VChip>
                  </h3>
                  <p class="text-base mb-0">
                    Standard plan for small to medium businesses
                  </p>
                </div>
              </div>
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <VAlert
                icon="tabler-alert-triangle"
                type="warning"
                variant="tonal"
                v-if="planDetails.days_remaining < 7"
              >
                <VAlertTitle class="mb-1">
                  We need your attention!
                </VAlertTitle>

                <span>Your plan requires update</span>
              </VAlert>
              <VAlert
                v-else
                icon="tabler-check"
                type="success"
                variant="tonal"
              >
                 <VAlertTitle class="mb-1">
                  Plan is Healthy
                </VAlertTitle>
                <span>You are covered for {{ planDetails.days_remaining }} days.</span>
              </VAlert>

              <!-- progress -->
              <h6 class="d-flex font-weight-medium text-body-1 text-high-emphasis mt-6 mb-1">
                <span>Days</span>
                <VSpacer />
                <span>{{ planDetails.days_consumed }} of {{ planDetails.total_days }} Days</span>
              </h6>

              <VProgressLinear
                color="primary"
                rounded
                :model-value="planDetails.progress_percent"
              />

              <p class="text-body-2 mt-1 mb-0">
                {{ planDetails.days_remaining }} days remaining until your plan requires update
              </p>
            </VCol>

            <VCol cols="12">
              <div class="d-flex flex-wrap gap-4">
                <VBtn @click="isPricingPlanDialogVisible = true">
                  upgrade plan
                </VBtn>

                <VBtn
                  color="error"
                  variant="tonal"
                  @click="isConfirmDialogVisible = true"
                >
                  Cancel Subscription
                </VBtn>
              </div>
            </VCol>
          </VRow>

          <!-- 👉 Confirm Dialog -->
          <ConfirmDialog
            v-model:is-dialog-visible="isConfirmDialogVisible"
            confirmation-question="Are you sure to cancel your subscription?"
            cancel-msg="Unsubscription Cancelled!!"
            cancel-title="Cancelled"
            confirm-msg="Your subscription cancelled successfully."
            confirm-title="Unsubscribed!"
          />

          <!-- 👉 plan and pricing dialog -->
          <PricingPlanDialog v-model:is-dialog-visible="isPricingPlanDialogVisible" />
        </VCardText>
      </VCard>
    </VCol>

    <!-- 👉 Payment Methods -->
    <VCol cols="12">
      <VCard title="Payment Methods">
        <VCardText>
          <div class="d-flex justify-space-between align-center mb-6">
            <h6 class="text-body-1 text-high-emphasis font-weight-medium">
              My Cards
            </h6>
            <VBtn
              size="small"
              @click="openAddCardDialog"
            >
              Add New Card
            </VBtn>
          </div>

          <!-- Loading Skeleton -->
          <VSkeletonLoader
            v-if="isLoadingPaymentMethods"
            type="image"
            height="150"
          />

          <!-- Error State -->
          <VAlert
            v-else-if="paymentMethodsError"
            type="error"
            variant="tonal"
            icon="tabler-alert-circle"
          >
            Failed to load payment methods. Please refresh the page.
          </VAlert>

          <!-- Cards List -->
          <div v-else-if="paymentMethods.length > 0" class="d-flex flex-column gap-y-6">
            <VCard
              v-for="card in paymentMethods"
              :key="card.id"
              flat
              color="rgba(var(--v-theme-on-surface),var(--v-hover-opacity))"
            >
              <VCardText class="d-flex flex-sm-row flex-column">
                <div class="text-no-wrap">
                  <img
                    :src="getCardImage(card.brand)"
                    height="25"
                  >
                  <h4 class="my-2 text-body-1 text-high-emphasis d-flex align-center">
                    <div class="me-4 font-weight-medium text-capitalize">
                      {{ card.brand }}
                    </div>
                    <VChip
                      v-if="card.is_default"
                      label
                      color="primary"
                      size="small"
                    >
                      Default
                    </VChip>
                  </h4>
                  <div class="text-body-1">
                    **** **** **** {{ card.last4 }}
                  </div>
                </div>

                <VSpacer />

                <div class="d-flex flex-column text-sm-end">
                  <div class="d-flex flex-wrap gap-4 order-sm-0 order-1">
                    <VBtn
                      v-if="!card.is_default"
                      variant="tonal"
                      size="small"
                      @click="setDefaultPaymentMethod(card.id)"
                    >
                      Set as Default
                    </VBtn>
                    <VBtn
                      color="error"
                      size="small"
                      variant="tonal"
                      @click="openDeleteCardDialog(card.id)"
                    >
                      Delete
                    </VBtn>
                  </div>
                  <span class="text-body-2 my-4 order-sm-1 order-0">
                    Card expires at {{ card.exp_month }}/{{ card.exp_year }}
                  </span>
                </div>
              </VCardText>
            </VCard>
          </div>

          <!-- Empty State -->
          <VAlert
            v-else
            type="info"
            variant="tonal"
            icon="tabler-info-circle"
          >
            No payment methods found. Add a card to get started!
          </VAlert>

          <!-- 👉 Add Card Dialog -->
          <VDialog
            v-model="isAddCardDialogVisible"
            max-width="600"
          >
            <VCard>
              <VCardTitle>Add New Payment Method</VCardTitle>
              <VCardText>
                <div id="card-element" style="padding: 16px; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 16px;"></div>
              </VCardText>
              <VCardActions>
                <VSpacer />
                <VBtn
                  color="secondary"
                  variant="tonal"
                  @click="isAddCardDialogVisible = false"
                >
                  Cancel
                </VBtn>
                <VBtn
                  color="primary"
                  @click="submitNewCard"
                  :loading="loadingCard"
                >
                  Add Card
                </VBtn>
              </VCardActions>
            </VCard>
          </VDialog>


          <!-- 👉 Delete Card Confirm Dialog -->
          <VDialog
            v-model="isDeleteCardDialogVisible"
            max-width="500"
          >
            <VCard class="text-center px-10 py-6">
              <VCardText>
                <VBtn
                  icon
                  variant="outlined"
                  color="warning"
                  class="my-4"
                  style="block-size: 88px; inline-size: 88px; pointer-events: none;"
                >
                  <span class="text-5xl">!</span>
                </VBtn>

                <h6 class="text-lg font-weight-medium">
                  Are you sure you want to delete this payment method?
                </h6>
              </VCardText>

              <VCardText class="d-flex align-center justify-center gap-2">
                <VBtn
                  variant="elevated"
                  @click="deletePaymentMethod"
                >
                  Confirm
                </VBtn>

                <VBtn
                  color="secondary"
                  variant="tonal"
                  @click="isDeleteCardDialogVisible = false"
                >
                  Cancel
                </VBtn>
              </VCardText>
            </VCard>
          </VDialog>
        </VCardText>
      </VCard>
    </VCol>

    <!-- 👉 Billing Address -->
    <VCol cols="12">
      <VCard title="Billing Address">
        <VCardText>
          <p class="text-body-2 text-medium-emphasis mb-6">
            <span class="d-inline-block me-1">📄</span>
            Billing Information (for Invoices) - This information will appear on your official receipts.
          </p>
          
          <!-- Loading Skeleton -->
          <VSkeletonLoader
            v-if="isLoadingBillingAddress"
            type="list-item-three-line"
          />

          <!-- Error State -->
          <VAlert
            v-else-if="billingAddressError"
            type="error"
            variant="tonal"
            icon="tabler-alert-circle"
          >
            Failed to load billing address. Please refresh the page.
          </VAlert>

          <!-- Billing Form -->
          <VForm v-else @submit.prevent="saveBillingAddress">
            <VRow>
              <!-- 👉 Address Line 1 -->
              <VCol cols="12">
                <AppTextField
                  v-model="billingAddress.line1"
                  label="Address Line 1"
                  placeholder="123 Main St"
                />
              </VCol>

              <!-- 👉 Address Line 2 -->
              <VCol cols="12">
                <AppTextField
                  v-model="billingAddress.line2"
                  label="Address Line 2 (Optional)"
                  placeholder="Apt 4B"
                />
              </VCol>

              <!-- 👉 City -->
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="billingAddress.city"
                  label="City"
                  placeholder="London"
                />
              </VCol>

              <!-- 👉 State -->
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="billingAddress.state"
                  label="State / Province (Optional)"
                  placeholder="Greater London"
                />
              </VCol>

              <!-- 👉 Postal Code -->
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="billingAddress.postal_code"
                  label="Postal Code"
                  placeholder="SW1A 1AA"
                />
              </VCol>

              <!-- 👉 Country -->
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="billingAddress.country"
                  label="Country"
                  :items="countryList"
                  placeholder="Select Country"
                />
              </VCol>

              <!-- 👉 Actions Button -->
              <VCol
                cols="12"
                class="d-flex flex-wrap gap-4"
              >
                <VBtn type="submit">
                  Save Address
                </VBtn>
                <VBtn
                  type="reset"
                  color="secondary"
                  variant="tonal"
                  @click="fetchBillingAddress"
                >
                  Reset
                </VBtn>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>

    <!-- 👉 Billing History -->
    <VCol cols="12">
      <BillingHistoryTable />
    </VCol>

    <!-- 👉 Snackbar for Notifications -->
    <VSnackbar
      v-model="snackbar"
      :color="snackbarColor"
      location="top end"
      :timeout="4000"
    >
      {{ snackbarMessage }}
      <template #actions>
        <VBtn
          color="white"
          variant="text"
          @click="snackbar = false"
        >
          Close
        </VBtn>
      </template>
    </VSnackbar>
  </VRow>
</template>

<style lang="scss">
.pricing-dialog {
  .pricing-title {
    font-size: 1.5rem !important;
  }

  .v-card {
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    box-shadow: none;
  }
}
</style>
