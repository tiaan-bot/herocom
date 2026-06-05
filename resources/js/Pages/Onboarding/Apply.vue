<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import { Check, ChevronLeft, ChevronRight, Loader2, Plus, Trash2, Building2, CreditCard } from 'lucide-vue-next'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import FormField from '@/components/FormField.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select'

interface Option {
  value: string
  label: string
}

type DocRule = 'always' | 'credit' | 'entity_company_cc' | 'has_vat'

type DocumentKey =
  | 'signed_application_form' | 'id_document' | 'cipc_registration'
  | 'vat_certificate' | 'bank_confirmation' | 'proof_of_address' | 'deed_of_surety'

interface DocumentTypeDef {
  value: DocumentKey
  label: string
  rule: DocRule
}

interface ApplyPageProps {
  termsVersion: string
  termsUrl: string
  accountTypes: Option[]
  entityTypes: Option[]
  turnoverBands: Option[]
  creditTermsDays: number[]
  documentTypes: DocumentTypeDef[]
}

interface PrincipalInput {
  full_name: string
  surname: string
  id_number: string
  shareholding_percent: string
  residential_address_line1: string
  residential_city: string
  residential_province: string
  residential_postal_code: string
  is_surety: boolean
  married_in_community: boolean
}

interface CgicPayload {
  banking: { bank: string; account_name: string; account_number: string; branch_code: string }
  disclosures: { judgements: boolean; liquidations: boolean; sureties_cessions: boolean; moratoriums: boolean }
}

// Mirrors StoreOnboardingApplicationRequest / SubmitOnboardingApplicationData.
interface ApplyForm {
  website: string // honeypot
  account_type_requested: string
  legal_name: string
  trading_name: string
  entity_type: string
  registration_number: string
  vat_number: string
  nature_of_business: string
  address_line1: string
  address_line2: string
  city: string
  province: string
  postal_code: string
  country_code: string
  currency: string
  contact_name: string
  contact_email: string
  contact_phone: string
  premises_owned: boolean
  landlord_name: string
  landlord_address: string
  landlord_tel: string
  period_at_address: string
  credit_limit_requested: string
  credit_terms_requested_days: string
  annual_turnover_band: string
  cgic_payload: CgicPayload
  principals: PrincipalInput[]
  documents: Record<DocumentKey, File | null>
  terms_version: string
  terms_accepted: boolean
  popia_consent: boolean
  credit_enquiry_consent: boolean
}

type StepKey = 'account' | 'company' | 'contact' | 'principals' | 'financials' | 'documents' | 'review'

interface Step {
  key: StepKey
  label: string
  creditOnly?: boolean
}

const props = defineProps<ApplyPageProps>()

const MAX_FILE_MB = 10
const ACCEPT = '.pdf,.jpg,.jpeg,.png'

const form = useForm<ApplyForm>({
  website: '',
  account_type_requested: '',
  legal_name: '', trading_name: '', entity_type: '', registration_number: '', vat_number: '', nature_of_business: '',
  address_line1: '', address_line2: '', city: '', province: '', postal_code: '', country_code: 'ZA', currency: 'ZAR',
  contact_name: '', contact_email: '', contact_phone: '',
  premises_owned: true, landlord_name: '', landlord_address: '', landlord_tel: '', period_at_address: '',
  credit_limit_requested: '', credit_terms_requested_days: '', annual_turnover_band: '',
  cgic_payload: {
    banking: { bank: '', account_name: '', account_number: '', branch_code: '' },
    disclosures: { judgements: false, liquidations: false, sureties_cessions: false, moratoriums: false },
  },
  principals: [],
  documents: {
    signed_application_form: null, id_document: null, cipc_registration: null,
    vat_certificate: null, bank_confirmation: null, proof_of_address: null, deed_of_surety: null,
  },
  terms_version: props.termsVersion,
  terms_accepted: false, popia_consent: false, credit_enquiry_consent: false,
})

// Inertia types errors by top-level key only; nested doc/principal keys need a string lookup.
function errorFor(key: string): string | undefined {
  return (form.errors as Record<string, string | undefined>)[key]
}

const isCredit = computed(() => form.account_type_requested === 'credit')
const fileErrors = ref<Record<string, string>>({})

const allSteps: Step[] = [
  { key: 'account', label: 'Account type' },
  { key: 'company', label: 'Company' },
  { key: 'contact', label: 'Contact' },
  { key: 'principals', label: 'Principals', creditOnly: true },
  { key: 'financials', label: 'Financials', creditOnly: true },
  { key: 'documents', label: 'Documents' },
  { key: 'review', label: 'Review' },
]
const steps = computed<Step[]>(() => allSteps.filter((s) => !s.creditOnly || isCredit.value))
const stepIndex = ref(0)
const current = computed<Step>(() => steps.value[stepIndex.value] ?? steps.value[0])
const isLastStep = computed(() => stepIndex.value === steps.value.length - 1)

// --- documents -------------------------------------------------------------
function docVisible(doc: DocumentTypeDef): boolean {
  return doc.rule === 'credit' ? isCredit.value : true
}
function docRequired(doc: DocumentTypeDef): boolean {
  switch (doc.rule) {
    case 'always': return true
    case 'credit': return isCredit.value
    case 'entity_company_cc': return ['company', 'close_corporation'].includes(form.entity_type)
    case 'has_vat': return !!form.vat_number
    default: return false
  }
}
const visibleDocs = computed<DocumentTypeDef[]>(() => props.documentTypes.filter(docVisible))

function onFile(type: DocumentKey, event: Event): void {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0] ?? null
  delete fileErrors.value[type]
  if (file) {
    const okType = /\.(pdf|jpe?g|png)$/i.test(file.name)
    const okSize = file.size <= MAX_FILE_MB * 1024 * 1024
    if (!okType) { fileErrors.value[type] = 'Must be a PDF, JPG or PNG.'; input.value = ''; return }
    if (!okSize) { fileErrors.value[type] = `Must be ${MAX_FILE_MB}MB or smaller.`; input.value = ''; return }
  }
  form.documents[type] = file
}

// --- principals ------------------------------------------------------------
function addPrincipal(): void {
  form.principals.push({
    full_name: '', surname: '', id_number: '', shareholding_percent: '',
    residential_address_line1: '', residential_city: '', residential_province: '', residential_postal_code: '',
    is_surety: true, married_in_community: false,
  })
}
function removePrincipal(i: number): void {
  form.principals.splice(i, 1)
}

// --- per-step gating -------------------------------------------------------
function filled(...vals: unknown[]): boolean {
  return vals.every((v) => v !== '' && v !== null && v !== undefined)
}
const stepComplete = computed<Record<StepKey, boolean>>(() => ({
  account: !!form.account_type_requested,
  company: filled(form.legal_name, form.entity_type, form.address_line1, form.city, form.province, form.postal_code)
    && (form.premises_owned || filled(form.landlord_name)),
  contact: filled(form.contact_name, form.contact_phone) && /^\S+@\S+\.\S+$/.test(form.contact_email),
  principals: form.principals.length > 0
    && form.principals.every((p) => filled(p.full_name, p.surname, p.id_number)),
  financials: filled(form.credit_limit_requested, form.credit_terms_requested_days, form.annual_turnover_band, form.cgic_payload.banking.bank),
  documents: visibleDocs.value.filter(docRequired).every((d) => form.documents[d.value]),
  review: form.terms_accepted && form.popia_consent && (!isCredit.value || form.credit_enquiry_consent),
}))
const canProceed = computed(() => stepComplete.value[current.value.key])

function next(): void {
  if (stepIndex.value === 0 && form.principals.length === 0 && isCredit.value) addPrincipal()
  if (!isLastStep.value && canProceed.value) stepIndex.value++
}
function prev(): void {
  if (stepIndex.value > 0) stepIndex.value--
}
function goTo(i: number): void {
  if (i <= stepIndex.value) stepIndex.value = i
}

// --- submit ----------------------------------------------------------------
const stepForField: Record<string, StepKey> = {
  account_type_requested: 'account',
  legal_name: 'company', trading_name: 'company', entity_type: 'company', registration_number: 'company',
  vat_number: 'company', nature_of_business: 'company', address_line1: 'company', address_line2: 'company',
  city: 'company', province: 'company', postal_code: 'company', premises_owned: 'company',
  landlord_name: 'company', landlord_address: 'company', landlord_tel: 'company', period_at_address: 'company',
  contact_name: 'contact', contact_email: 'contact', contact_phone: 'contact',
  principals: 'principals',
  credit_limit_requested: 'financials', credit_terms_requested_days: 'financials',
  annual_turnover_band: 'financials', cgic_payload: 'financials',
  documents: 'documents',
  terms_accepted: 'review', popia_consent: 'review', credit_enquiry_consent: 'review', terms_version: 'review',
}

function jumpToFirstError(): void {
  const keys = Object.keys(form.errors)
  if (!keys.length) return
  const stepKeys = steps.value.map((s) => s.key)
  let target: number | null = null
  for (const field of keys) {
    const root = field.split('.')[0]
    const step = stepForField[root]
    if (step && stepKeys.includes(step)) {
      const idx = stepKeys.indexOf(step)
      if (target === null || idx < target) target = idx
    }
  }
  if (target !== null) stepIndex.value = target
}

function submit(): void {
  form.transform((data) => {
    if (data.account_type_requested !== 'credit') {
      return {
        ...data,
        principals: [],
        credit_limit_requested: null,
        credit_terms_requested_days: null,
        annual_turnover_band: null,
        cgic_payload: null,
        credit_enquiry_consent: false,
      }
    }
    return data
  })

  form.post('/apply', {
    forceFormData: true,
    onError: () => jumpToFirstError(),
  })
}
</script>

<template>
  <Head title="Apply — Herocom Distribution" />
  <PublicLayout>
    <div class="mb-8">
      <h1 class="text-2xl font-semibold tracking-tight">Reseller application</h1>
      <p class="mt-1 text-sm text-muted-foreground">
        Tell us about your business. Approved resellers get access to our catalogue and trade pricing.
      </p>
    </div>

    <!-- Stepper -->
    <ol class="mb-8 flex flex-wrap gap-x-2 gap-y-2 text-sm">
      <li v-for="(s, i) in steps" :key="s.key" class="flex items-center gap-2">
        <button
          type="button"
          class="flex items-center gap-2 rounded-md px-2 py-1 transition-colors"
          :class="i === stepIndex ? 'bg-primary/10 text-foreground' : 'text-muted-foreground hover:text-foreground'"
          @click="goTo(i)"
        >
          <span
            class="grid size-5 place-items-center rounded-full text-xs"
            :class="i < stepIndex ? 'bg-primary text-primary-foreground' : i === stepIndex ? 'border border-primary text-primary' : 'border'"
          >
            <Check v-if="i < stepIndex" class="size-3" />
            <template v-else>{{ i + 1 }}</template>
          </span>
          {{ s.label }}
        </button>
        <ChevronRight v-if="i < steps.length - 1" class="size-4 text-muted-foreground/50" />
      </li>
    </ol>

    <form @submit.prevent="submit">
      <!-- Honeypot -->
      <div class="absolute -left-[9999px]" aria-hidden="true">
        <label>Website<input v-model="form.website" type="text" tabindex="-1" autocomplete="off" /></label>
      </div>

      <!-- Step: Account type -->
      <div v-show="current.key === 'account'" class="grid gap-4 sm:grid-cols-2">
        <button
          v-for="t in accountTypes" :key="t.value" type="button"
          class="rounded-lg border p-5 text-left transition-all hover:border-primary"
          :class="form.account_type_requested === t.value ? 'border-primary ring-2 ring-primary/20' : ''"
          @click="form.account_type_requested = t.value"
        >
          <component :is="t.value === 'credit' ? CreditCard : Building2" class="mb-3 size-6 text-primary" />
          <div class="font-medium">{{ t.value === 'credit' ? 'Credit account' : 'COD (pay upfront)' }}</div>
          <p class="mt-1 text-sm text-muted-foreground">
            <template v-if="t.value === 'credit'">
              Apply for a credit facility. Requires principals' details, financials, a Deed of Suretyship and a CGIC credit check.
            </template>
            <template v-else>
              Pay by EFT on each order. Fastest to onboard — no credit check or suretyship.
            </template>
          </p>
        </button>
      </div>

      <!-- Step: Company -->
      <div v-show="current.key === 'company'" class="space-y-5">
        <div class="grid gap-4 sm:grid-cols-2">
          <FormField label="Legal / registered name" :error="form.errors.legal_name" required>
            <Input v-model="form.legal_name" />
          </FormField>
          <FormField label="Trading name" :error="form.errors.trading_name" hint="If different from legal name.">
            <Input v-model="form.trading_name" />
          </FormField>
          <FormField label="Entity type" :error="form.errors.entity_type" required>
            <Select v-model="form.entity_type">
              <SelectTrigger class="w-full"><SelectValue placeholder="Select…" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="o in entityTypes" :key="o.value" :value="o.value">{{ o.label }}</SelectItem>
              </SelectContent>
            </Select>
          </FormField>
          <FormField label="Nature of business" :error="form.errors.nature_of_business">
            <Input v-model="form.nature_of_business" />
          </FormField>
          <FormField label="Registration number" :error="form.errors.registration_number" hint="Not applicable to sole proprietors.">
            <Input v-model="form.registration_number" />
          </FormField>
          <FormField label="VAT number" :error="form.errors.vat_number" hint="Leave blank if not VAT-registered.">
            <Input v-model="form.vat_number" />
          </FormField>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <FormField label="Address line 1" :error="form.errors.address_line1" required>
            <Input v-model="form.address_line1" />
          </FormField>
          <FormField label="Address line 2" :error="form.errors.address_line2">
            <Input v-model="form.address_line2" />
          </FormField>
          <FormField label="City" :error="form.errors.city" required><Input v-model="form.city" /></FormField>
          <FormField label="Province" :error="form.errors.province" required><Input v-model="form.province" /></FormField>
          <FormField label="Postal code" :error="form.errors.postal_code" required><Input v-model="form.postal_code" /></FormField>
        </div>

        <div class="flex items-center gap-2">
          <Checkbox id="premises" v-model="form.premises_owned" />
          <Label for="premises">We own these premises</Label>
        </div>
        <div v-if="!form.premises_owned" class="grid gap-4 rounded-md border bg-background p-4 sm:grid-cols-3">
          <FormField label="Landlord name" :error="form.errors.landlord_name"><Input v-model="form.landlord_name" /></FormField>
          <FormField label="Landlord address" :error="form.errors.landlord_address"><Input v-model="form.landlord_address" /></FormField>
          <FormField label="Landlord tel" :error="form.errors.landlord_tel"><Input v-model="form.landlord_tel" /></FormField>
        </div>
      </div>

      <!-- Step: Contact -->
      <div v-show="current.key === 'contact'" class="space-y-5">
        <p class="text-sm text-muted-foreground">This person becomes the account owner once approved.</p>
        <div class="grid gap-4 sm:grid-cols-2">
          <FormField label="Contact name" :error="form.errors.contact_name" required><Input v-model="form.contact_name" /></FormField>
          <FormField label="Email" :error="form.errors.contact_email" required><Input v-model="form.contact_email" type="email" /></FormField>
          <FormField label="Phone" :error="form.errors.contact_phone" required><Input v-model="form.contact_phone" /></FormField>
        </div>
      </div>

      <!-- Step: Principals (credit) -->
      <div v-show="current.key === 'principals'" class="space-y-4">
        <p class="text-sm text-muted-foreground">
          Directors / members / owners. <strong class="text-foreground">These people will stand as personal sureties</strong> for the credit facility.
        </p>
        <Card v-for="(p, i) in form.principals" :key="i">
          <CardHeader class="flex-row items-center justify-between">
            <CardTitle class="text-base">Principal {{ i + 1 }}</CardTitle>
            <Button type="button" variant="ghost" size="icon-sm" @click="removePrincipal(i)"><Trash2 class="size-4" /></Button>
          </CardHeader>
          <CardContent class="grid gap-4 sm:grid-cols-2">
            <FormField label="First name" :error="errorFor(`principals.${i}.full_name`)" required><Input v-model="p.full_name" /></FormField>
            <FormField label="Surname" :error="errorFor(`principals.${i}.surname`)" required><Input v-model="p.surname" /></FormField>
            <FormField label="SA ID number" :error="errorFor(`principals.${i}.id_number`)" required hint="Stored encrypted."><Input v-model="p.id_number" /></FormField>
            <FormField label="Shareholding %" :error="errorFor(`principals.${i}.shareholding_percent`)"><Input v-model="p.shareholding_percent" type="number" min="0" max="100" /></FormField>
            <FormField label="Residential address" class="sm:col-span-2"><Input v-model="p.residential_address_line1" /></FormField>
            <FormField label="City"><Input v-model="p.residential_city" /></FormField>
            <FormField label="Postal code"><Input v-model="p.residential_postal_code" /></FormField>
            <div class="flex items-center gap-2"><Checkbox :id="`mic-${i}`" v-model="p.married_in_community" /><Label :for="`mic-${i}`">Married in community of property</Label></div>
          </CardContent>
        </Card>
        <Button type="button" variant="outline" @click="addPrincipal"><Plus class="size-4" /> Add principal</Button>
      </div>

      <!-- Step: Financials (credit) -->
      <div v-show="current.key === 'financials'" class="space-y-5">
        <div class="grid gap-4 sm:grid-cols-3">
          <FormField label="Credit limit requested (ZAR)" :error="form.errors.credit_limit_requested" required><Input v-model="form.credit_limit_requested" type="number" min="0" /></FormField>
          <FormField label="Payment terms" :error="form.errors.credit_terms_requested_days" required>
            <Select v-model="form.credit_terms_requested_days">
              <SelectTrigger class="w-full"><SelectValue placeholder="Select…" /></SelectTrigger>
              <SelectContent><SelectItem v-for="d in creditTermsDays" :key="d" :value="String(d)">{{ d }} days</SelectItem></SelectContent>
            </Select>
          </FormField>
          <FormField label="Annual turnover" :error="form.errors.annual_turnover_band" required>
            <Select v-model="form.annual_turnover_band">
              <SelectTrigger class="w-full"><SelectValue placeholder="Select…" /></SelectTrigger>
              <SelectContent><SelectItem v-for="o in turnoverBands" :key="o.value" :value="o.value">{{ o.label }}</SelectItem></SelectContent>
            </Select>
          </FormField>
        </div>

        <Card>
          <CardHeader><CardTitle class="text-base">Banking details</CardTitle><CardDescription>Used for the CGIC credit submission only.</CardDescription></CardHeader>
          <CardContent class="grid gap-4 sm:grid-cols-2">
            <FormField label="Bank" required><Input v-model="form.cgic_payload.banking.bank" /></FormField>
            <FormField label="Account name"><Input v-model="form.cgic_payload.banking.account_name" /></FormField>
            <FormField label="Account number"><Input v-model="form.cgic_payload.banking.account_number" /></FormField>
            <FormField label="Branch code"><Input v-model="form.cgic_payload.banking.branch_code" /></FormField>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle class="text-base">Legal disclosures</CardTitle><CardDescription>Tick any that apply to the business or its principals.</CardDescription></CardHeader>
          <CardContent class="space-y-3">
            <div class="flex items-center gap-2"><Checkbox id="d1" v-model="form.cgic_payload.disclosures.judgements" /><Label for="d1">Any judgements against the business or principals</Label></div>
            <div class="flex items-center gap-2"><Checkbox id="d2" v-model="form.cgic_payload.disclosures.liquidations" /><Label for="d2">Previous liquidations / business rescue</Label></div>
            <div class="flex items-center gap-2"><Checkbox id="d3" v-model="form.cgic_payload.disclosures.sureties_cessions" /><Label for="d3">Existing sureties, cessions or notarial bonds</Label></div>
            <div class="flex items-center gap-2"><Checkbox id="d4" v-model="form.cgic_payload.disclosures.moratoriums" /><Label for="d4">Any current payment moratoriums</Label></div>
          </CardContent>
        </Card>
      </div>

      <!-- Step: Documents -->
      <div v-show="current.key === 'documents'" class="space-y-4">
        <p class="text-sm text-muted-foreground">Upload PDF, JPG or PNG files, up to {{ MAX_FILE_MB }}MB each.</p>
        <div v-for="d in visibleDocs" :key="d.value" class="rounded-md border bg-background p-4">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <span class="font-medium">{{ d.label }}</span>
              <span v-if="docRequired(d)" class="ml-2 text-xs font-medium text-destructive">Required</span>
              <span v-else class="ml-2 text-xs text-muted-foreground">Optional</span>
              <p v-if="form.documents[d.value]" class="mt-0.5 text-xs text-muted-foreground">{{ form.documents[d.value]?.name }}</p>
            </div>
            <input :id="`f-${d.value}`" type="file" :accept="ACCEPT" class="text-sm file:mr-3 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:py-1.5 file:text-sm" @change="(e) => onFile(d.value, e)" />
          </div>
          <p v-if="fileErrors[d.value]" class="mt-1 text-xs font-medium text-destructive">{{ fileErrors[d.value] }}</p>
          <p v-if="errorFor(`documents.${d.value}`)" class="mt-1 text-xs font-medium text-destructive">{{ errorFor(`documents.${d.value}`) }}</p>
        </div>
      </div>

      <!-- Step: Review & consent -->
      <div v-show="current.key === 'review'" class="space-y-5">
        <Card>
          <CardHeader><CardTitle class="text-base">Summary</CardTitle></CardHeader>
          <CardContent class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
            <div><span class="text-muted-foreground">Account type:</span> {{ isCredit ? 'Credit' : 'COD' }}</div>
            <div><span class="text-muted-foreground">Legal name:</span> {{ form.legal_name }}</div>
            <div><span class="text-muted-foreground">Contact:</span> {{ form.contact_name }}</div>
            <div><span class="text-muted-foreground">Email:</span> {{ form.contact_email }}</div>
            <div v-if="isCredit"><span class="text-muted-foreground">Principals:</span> {{ form.principals.length }}</div>
            <div v-if="isCredit"><span class="text-muted-foreground">Credit limit:</span> R{{ form.credit_limit_requested }}</div>
          </CardContent>
        </Card>

        <div v-if="Object.keys(form.errors).length" class="rounded-md border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">
          Please review the highlighted fields — some steps need attention before you can submit.
        </div>

        <div class="space-y-3">
          <div class="flex items-start gap-2">
            <Checkbox id="terms" v-model="form.terms_accepted" />
            <Label for="terms" class="font-normal leading-snug">
              I accept the <a :href="termsUrl" target="_blank" class="text-primary underline">Standard Terms &amp; Conditions of Sale</a> (version {{ termsVersion }}).
            </Label>
          </div>
          <div class="flex items-start gap-2">
            <Checkbox id="popia" v-model="form.popia_consent" />
            <Label for="popia" class="font-normal leading-snug">I consent to Herocom processing this information in line with POPIA.</Label>
          </div>
          <div v-if="isCredit" class="flex items-start gap-2">
            <Checkbox id="enquiry" v-model="form.credit_enquiry_consent" />
            <Label for="enquiry" class="font-normal leading-snug">I consent to a credit enquiry and submission to CGIC for credit assessment.</Label>
          </div>
        </div>
      </div>

      <!-- Nav -->
      <div class="mt-8 flex items-center justify-between border-t pt-5">
        <Button type="button" variant="ghost" :disabled="stepIndex === 0" @click="prev"><ChevronLeft class="size-4" /> Back</Button>
        <Button v-if="!isLastStep" type="button" :disabled="!canProceed" @click="next">Continue <ChevronRight class="size-4" /></Button>
        <Button v-else type="submit" :disabled="!canProceed || form.processing">
          <Loader2 v-if="form.processing" class="size-4 animate-spin" />
          Submit application
        </Button>
      </div>
    </form>
  </PublicLayout>
</template>
