import React, { useCallback, useEffect } from 'react'
import Alert from '@webstollen/react-jtl-plugin/lib/components/Alert'
import { faExclamation } from '@fortawesome/pro-solid-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome'
import { Loading } from '@webstollen/react-jtl-plugin/lib'
import Details from './Details'
import { faSync, faTimes } from '@fortawesome/pro-regular-svg-icons'
import useMollie from '../../../hooks/useMollie'
import useOrder from '../../../hooks/useOrder'
import useErrorSnack from '../../../hooks/useErrorSnack'
import OrderLines from './OrderLines'
import Payments from './Payments'
import Shipments from './Shipments'
import Refunds from './Refunds'
import useQueue from '../../../hooks/useQueue'
import Queue from './Queue'
import Logs from './Logs'

export type OrderDetailsProps = {
  id: string
  onClose?: (event: React.MouseEvent<HTMLDivElement, MouseEvent>) => void
}
const OrderDetails = (props: OrderDetailsProps) => {
  const mollie = useMollie(props.id)
  const order = useOrder(props.id)
  const queue = useQueue(props.id)

  const [showError] = useErrorSnack()

  const reload = () => {
    console.debug('(OrderDetails->reload)')
    mollie.load().catch((e) => showError(`${e}`))
    order.load().catch((e) => showError(`${e}`))
    queue.load().catch((e) => showError(`${e}`))
  }

  useEffect(() => {
    reload()
  }, [])

  if (mollie.error !== null || order.error !== null) {
    return (
      <div className="relative flex-col mb-3 rounded-md w-full">
        <div className="flex flex-row bg-black p-3 rounded-md text-white font-bold text-2xl">
          <div className="flex-grow">
            Bestellung: {order.data?.cBestellNr} (<pre className="inline text-ws_gray-light">{props.id}</pre>)
          </div>
          <div onClick={reload} className="cursor-pointer mr-2">
            <FontAwesomeIcon icon={faSync} spin={order.loading} size={'sm'} />
          </div>
          <div onClick={props.onClose} className="cursor-pointer">
            <FontAwesomeIcon icon={faTimes} />
          </div>
        </div>
        {order.error != null && (
          <Alert variant={'error'} icon={{ icon: faExclamation }}>
            Fehler beim laden der Bestellung "{props.id}": {order.error}
          </Alert>
        )}
        {mollie.error != null && (
          <Alert variant={'error'} icon={{ icon: faExclamation }}>
            Fehler beim Laden von Mollie "{props.id}": {mollie.error}
          </Alert>
        )}
      </div>
    )
  }

  return (
    <div className="relative mb-3 w-full">
      <Loading loading={order.loading}>
        <div className="flex flex-row bg-black p-3 rounded-md text-white font-bold text-2xl">
          <div className="flex-grow">
            Bestellung: <span title={order.data?.kBestellung}>{order.data?.cBestellNr}</span> (
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
          {mollie && <Details mollie={mollie} />}

          {mollie && mollie?.data?.resource === 'payment' ? (
            <></>
          ) : (
            <>
              {mollie.data && <OrderLines mollie={mollie} />}

              {mollie.data && <Payments mollie={mollie} />}

              {mollie.data && order && order?.data && (
                <Shipments kBestellung={order?.data.kBestellung} mollie={mollie} />
              )}
            </>
          )}

          {mollie.data && <Refunds mollie={mollie} />}

          {queue.data && <Queue queue={queue} />}

          {mollie.data && order?.data && <Logs order={order} mollie={mollie} />}
        </div>
      </Loading>
    </div>
  )
}

export default OrderDetails
