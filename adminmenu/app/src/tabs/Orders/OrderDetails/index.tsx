import React, { useCallback, useEffect } from 'react'
import Alert from '@webstollen/react-jtl-plugin/lib/components/Alert'
import { faExclamation } from '@fortawesome/pro-solid-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome'
import { Loading } from '@webstollen/react-jtl-plugin/lib'
import Payments from './Payments'
import Details from './Details'
import OrderLines from './OrderLines'
import Shipments from './Shipments'
import { faSync, faTimes } from '@fortawesome/pro-regular-svg-icons'
import Queue from './Queue'
import Refunds from './Refunds'
import Logs from './Logs'
import useOrder from '../../../hooks/useOrder'

export type OrderDetailsProps = {
  id: string
  onClose?: (event: React.MouseEvent<HTMLDivElement, MouseEvent>) => void
}
const OrderDetails = (props: OrderDetailsProps) => {
  const order = useOrder(props.id)
  console.debug('(OrderDetails->render)', order)

  const reload = useCallback(async () => {
    console.debug('(OrderDetails->reload)')
    await order.load()
  }, [order.load])

  useEffect(() => {
    reload().then(() => console.debug('(OrderDetails->useEffect) reloaded'))
  }, [reload])

  if (order.error !== null) {
    return (
      <div className="relative flex-col mb-3 rounded-md w-full">
        <div className="flex flex-row bg-black p-3 rounded-md text-white font-bold text-2xl">
          <div className="flex-grow">
            Bestellung: {order.data?.order.cBestellNr} (<pre className="inline text-ws_gray-light">{props.id}</pre>)
          </div>
          <div onClick={reload} className="cursor-pointer mr-2">
            <FontAwesomeIcon icon={faSync} spin={order.loading} size={'sm'} />
          </div>
          <div onClick={props.onClose} className="cursor-pointer">
            <FontAwesomeIcon icon={faTimes} />
          </div>
        </div>
        <Alert variant={'error'} icon={{ icon: faExclamation }}>
          Fehler beim laden der Bestellung "{props.id}": {order.error}
        </Alert>
      </div>
    )
  }

  return (
    <div className="relative mb-3 w-full">
      <Loading loading={order.loading}>
        <div className="flex flex-row bg-black p-3 rounded-md text-white font-bold text-2xl">
          <div className="flex-grow">
            Bestellung: <span title={order.data?.order.kBestellung}>{order.data?.order.cBestellNr}</span> (
            <pre className="inline text-ws_gray-light">{props.id}</pre>)
          </div>
          <div onClick={reload} className="cursor-pointer mr-2">
            <FontAwesomeIcon icon={faSync} spin={order.loading} size={'sm'} />
          </div>
          <div onClick={props.onClose} className="cursor-pointer">
            <FontAwesomeIcon icon={faTimes} />
          </div>
        </div>
        <div className=" rounded-md">
          {order?.data?.mollie && <Details mollie={order.data.mollie} />}

          {order?.data?.mollie.resource === 'payment' ? (
            <>{/* PAYMENT API */}</>
          ) : (
            <>
              {/* ORDER API */}

              {order?.data?.mollie && <OrderLines reload={reload} mollie={order.data.mollie} />}

              {order?.data?.mollie && <Payments mollie={order?.data.mollie} />}

              {order?.data?.mollie && order?.data?.bestellung && (
                <Shipments kBestellung={order?.data.bestellung.kBestellung} mollie={order?.data.mollie} />
              )}
            </>
          )}

          {order?.data?.mollie && <Refunds reload={reload} mollie={order?.data.mollie} />}

          {order?.data?.logs && <Queue data={order?.data.logs} />}

          {order?.data?.mollie && order?.data?.bestellung && (
            <Logs orderId={order?.data.mollie.id} kBestellung={order?.data.bestellung.kBestellung} />
          )}
        </div>
      </Loading>
    </div>
  )
}

export default OrderDetails
