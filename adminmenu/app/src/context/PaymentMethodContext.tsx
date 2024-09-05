import React, {createContext, useCallback, useContext, useEffect, useState, ReactNode} from 'react'
import {MergedPaymentMethodsMappedByMollieId} from "../types/PaymentMethod";
import useApi from "@webstollen/react-jtl-plugin/lib/hooks/useAPI";

type PaymentMethodContextType = { paymentMethods?: MergedPaymentMethodsMappedByMollieId, reFetchPaymentMethods: () => void }

const PaymentMethodContext = createContext<PaymentMethodContextType>({
    paymentMethods: undefined,
    reFetchPaymentMethods: () => {
        throw new Error('paymentMethods not yet loaded')
    }
})

function PaymentMethodProvider({children}: {children: ReactNode}) {
    const api = useApi()
    const [paymentMethods, setPaymentMethods] = useState<MergedPaymentMethodsMappedByMollieId | undefined>()

    const fetchPaymentMethods = useCallback(() => {
        api
            .run('mollie', 'methods')
            .then((res) => {
                setPaymentMethods(res.data.data)
            })
            .catch(console.error)
    }, [api])

    useEffect(() => {
        fetchPaymentMethods();
    }, [fetchPaymentMethods]);

    return <PaymentMethodContext.Provider value={{paymentMethods: paymentMethods, reFetchPaymentMethods: fetchPaymentMethods}}>{children}</PaymentMethodContext.Provider>
}

function usePaymentMethods() {
    const context = useContext(PaymentMethodContext)
    if (context === undefined) {
        throw new Error('usePaymentMethods must be used within the correct context provider')
    }
    return context
}

export {PaymentMethodProvider, usePaymentMethods}