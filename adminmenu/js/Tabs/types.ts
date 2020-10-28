export enum MollieStatus {
    OPEN = 'open',
    CANCELED = 'canceled',
    PENDING = 'pending',
    AUTHORIZED = 'authorized',
    EXPIRED = 'expired',
    FAILED = 'failed',
    PAID = 'paid',
}

export type Order = {
    kId: number
    kBestellung: number
    cTransactionId: string
    cOrderId: string
    cThirdId: string
    cBestellNr: string
    bTest: boolean
    cHash: string
    cStatus: MollieStatus
    bSynced: boolean
    dCreated: string
    dModified: string
    kWaehrung: number
    cJTLStatus: number
    fGesamtsumme: number
}

export type OrderDetails = {
    [key:string]: any
}