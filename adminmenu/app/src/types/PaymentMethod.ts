export type PaymentMethod = {
    kZahlungsart: number
    cName?: string
    cModulId?: string
    cKundengruppen?: string
    cZusatzschrittTemplate: string
    cPluginTemplate?: string
    cBild: string
    nSort: "0" | "1"
    nMailSenden: number
    nActive: "0" | "1"
    cAnbieter: string
    cTSCode: string
    nWaehrendBestellung: "0" | "1"
    nCURL: "0" | "1"
    nSOAP: "0" | "1"
    nSOCKETS: "0" | "1"
    nNutzbar: "0" | "1"
    shipping: Array<Record<string, unknown>>
    requirements: string
}

/**
 * Merged object of info from shop and Mollie about payment method
 */
    export type MergedPaymentMethodObject = {
    log?: number
    linkToSettingsPage: string
    mollie: Record<string, unknown>
    duringCheckout?: boolean
    allowDuringCheckout?: boolean
    paymentMethod: PaymentMethod
    linkedShippingMethods: Array<Record<string, string>>
    api?: 'order' | 'payment'
    components?: string
    dueDays?: number
}

export type MergedPaymentMethodsMappedByMollieId = Record<string, MergedPaymentMethodObject>
