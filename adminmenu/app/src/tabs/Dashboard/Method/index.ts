import Valid from "./Valid";
import Invalid from "./Invalid";

export type MethodProps = {
    api?: "order" | "payment"
    duringCheckout?: boolean
    allowDuringCheckout?: boolean
    mollie: Record<string, any>
    paymentMethod: false | Record<string, any>
    settings: string
    shipping: Array<Record<string, any>>
    components?: string
    dueDays?: number
}

export {Valid, Invalid}