import React, { useCallback, useEffect } from 'react'
import Alert from '@webstollen/react-jtl-plugin/lib/components/Alert'
import { faExclamation } from '@fortawesome/pro-solid-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome'
import { Loading } from '@webstollen/react-jtl-plugin/lib'
import Details from './Details'
import useMollie from '../../../hooks/useMollie'
import { faSync, faTimes } from '@fortawesome/pro-regular-svg-icons'
import useOrder from '../../../hooks/useOrder'
import OrderLines from './OrderLines'
import Payments from './Payments'
import Shipments from './Shipments'
import Refunds from './Refunds'
import Queue from './Queue'
import Logs from './Logs'
import MollieContext from '../../../context/MollieContext'

export type OrderDetailsProps = {
  id: string
  onClose?: (event: React.MouseEvent<HTMLDivElement, MouseEvent>) => void
}
const OrderDetails = (props: OrderDetailsProps) => {
  const mollie = useMollie(props.id, true)
  const { loading: orderLoading, load: orderLoad, error: orderError, data: orderData } = useOrder(props.id)

  const reload = useCallback(() => {
    orderLoad().catch(alert)
  }, [orderLoad])

  useEffect(() => {
    orderLoad().catch(alert)
  }, [orderLoad])

  if (mollie.error !== null || orderError !== null) {
    return (
      <div className="relative flex-col mb-3 rounded-md w-full">
        <div className="flex flex-row bg-black p-3 rounded-md text-white font-bold text-2xl">
          <div className="flex-grow">
            Bestellung: {orderData?.cBestellNr} (<pre className="inline text-ws_gray-light">{props.id}</pre>)
          </div>
          <div onClick={reload} className="cursor-pointer mr-2">
            <FontAwesomeIcon icon={faSync} spin={orderLoading} size={'sm'} />
          </div>
          <div onClick={props.onClose} className="cursor-pointer">
            <FontAwesomeIcon icon={faTimes} />
          </div>
        </div>
        {orderError != null && (
          <Alert variant={'error'} icon={{ icon: faExclamation }}>
            Fehler beim laden der Bestellung "{props.id}": {orderError}
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
    <div className="relative mb-3 w-full relative">
      <Loading loading={orderLoading}>
        <div className="flex flex-row bg-black p-3 rounded-md text-white font-bold text-2xl">
          <div className="flex-grow">
            Bestellung: <span title={orderData?.kBestellung}>{orderData?.cBestellNr}</span> (
            <pre className="inline text-ws_gray-light">{props.id}</pre>)
          </div>
          <div onClick={reload} className="cursor-pointer mr-2">
            <FontAwesomeIcon icon={faSync} spin={orderLoading} size={'sm'} />
          </div>
          <div onClick={props.onClose} className="cursor-pointer">
            <FontAwesomeIcon icon={faTimes} />
          </div>
        </div>
        <div className=" rounded-md relative">
          <Loading loading={mollie.loading}>
            <MollieContext.Provider value={mollie}>
              <Details />
              {mollie && mollie?.data?.resource === 'payment' ? (
                <></>
              ) : (
                <>
                  <OrderLines />
                  <Payments />
                  {orderData && <Shipments kBestellung={orderData?.kBestellung} />}
                </>
              )}
              <Refunds />
              {mollie?.data && orderData && <Logs kBestellung={orderData.kBestellung} mollieId={mollie.data.id} />}
              <Queue id={props.id} />
            </MollieContext.Provider>
          </Loading>
        </div>
      </Loading>
    </div>
  )
}

export default OrderDetails
