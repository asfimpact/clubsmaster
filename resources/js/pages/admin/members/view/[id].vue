<script setup>
const route = useRoute('admin-members-view-id')
const memberId = route.params.id

definePage({
  meta: {
    action: 'manage',
    subject: 'Admin',
  },
})

const member = ref(null)

const fetchMemberDetails = async () => {
  // We'll implement this later
  console.log('Fetching details for member:', memberId)
}

onMounted(() => {
  fetchMemberDetails()
})
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard title="Member Details Placeholder">
        <VCardText>
          Viewing details for member ID: {{ memberId }}. 
          Detailed bio and membership info will be implemented here.
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
