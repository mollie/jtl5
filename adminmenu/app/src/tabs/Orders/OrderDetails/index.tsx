import React, { useCallback, useEffect, useState } from 'react'
import useApi from '@webstollen/react-jtl-plugin/lib/hooks/useAPI'
import Alert from '@webstollen/react-jtl-plugin/lib/components/Alert'
import { faExclamation } from '@fortawesome/pro-solid-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome'
import { Loading } from '@webstollen/react-jtl-plugin/lib'
import { ApiError } from '../../../helper'
import Payments from './Payments'
import Details from './Details'
import OrderLines from './OrderLines'
import Shipments from './Shipments'
import { faSync, faTimes } from '@fortawesome/pro-regular-svg-icons'
import Queue from './Queue'
import Refunds from './Refunds'
import Logs from './Logs'

export type OrderDetailsProps = {
  id: string
  onClose?: (event: React.MouseEvent<HTMLDivElement, MouseEvent>) => void
}
const OrderDetails = (props: OrderDetailsProps) => {
  const [data, setData] = useState<null | Record<string, any>>(null)
  const [error, setError] = useState<ApiError | null>(null)
  const [loading, setLoading] = useState(false)
  const api = useApi()

  const loadOrder = useCallback(() => {
    setError(null)
    setData(null)
    if (props.id) {
      setLoading(true)
      api
        .run('orders', 'one', {
          id: props.id,
        })
        .then((res) => {
          setData(res.data.data)
          setError(null)
        })
        .catch(setError)
        .finally(() => setLoading(false))
    }
  }, [api, props.id])

  useEffect(loadOrder, [loadOrder])

  if (error !== null) {
    return (
      <div className="relative flex-col mb-3 rounded-md w-full">
        <div className="flex-row bg-black p-3 rounded-md text-white font-bold text-2xl">
          <div className="flex-grow">
            Bestellung: {data?.order.cBestellNr} (<pre className="inline text-ws_gray-light">{props.id}</pre>)
          </div>
          <div onClick={loadOrder} className="cursor-pointer mr-2">
            <FontAwesomeIcon icon={faSync} spin={loading} size={'sm'} />
          </div>
          <div onClick={props.onClose} className="cursor-pointer">
            <FontAwesomeIcon icon={faTimes} />
          </div>
        </div>
        <Alert variant={'error'} icon={{ icon: faExclamation }}>
          Fehler beim laden der Bestellung "{props.id}": {error.message}
        </Alert>
      </div>
    )
  }

  return (
    <div className="relative flex-col mb-3 rounded-md w-full">
      <Loading loading={loading} className="rounded-md">
        <div className="flex-row bg-black p-3 rounded-md text-white font-bold text-2xl">
          <div className="flex-grow">
            Bestellung: <span title={data?.order.kBestellung}>{data?.order.cBestellNr}</span> (
            <pre className="inline text-ws_gray-light">{props.id}</pre>)
          </div>
          <div onClick={loadOrder} className="cursor-pointer mr-2">
            <FontAwesomeIcon icon={faSync} spin={loading} size={'sm'} />
          </div>
          <div onClick={props.onClose} className="cursor-pointer">
            <FontAwesomeIcon icon={faTimes} />
          </div>
        </div>
        <div className=" rounded-md">
          {data && data.mollie && <Details mollie={data.mollie} />}

          {data && data.mollie.resource === 'payment' ? (
            <>{/* PAYMENT API */}</>
          ) : (
            <>
              {/* ORDER API */}

              {data && data.mollie && <OrderLines mollie={data.mollie} />}

              {data && data.mollie && <Payments mollie={data.mollie} />}

              {data && data.mollie && data.bestellung && (
                <Shipments kBestellung={data.bestellung.kBestellung} mollie={data.mollie} />
              )}
            </>
          )}

          {data && data.mollie && <Refunds mollie={data.mollie} />}

          {data && data.logs && <Queue data={data.logs} />}

          {data && data.mollie && data.bestellung && (
            <Logs orderId={data.mollie.id} kBestellung={data.bestellung.kBestellung} />
          )}
        </div>
      </Loading>
    </div>
  )
}

export default OrderDetails
