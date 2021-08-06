import React, { useCallback, useEffect, useState } from 'react'
import useApi from '@webstollen/react-jtl-plugin/lib/hooks/useAPI'
import { formatAmount, Loading, usePluginInfo } from '@webstollen/react-jtl-plugin/lib'
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome'
import {
  faCreditCard,
  faEnvelopeOpenDollar,
  faEnvelopeOpenText,
  faExternalLink,
  faMoneyBill,
  faShippingFast,
  faSync,
} from '@fortawesome/pro-regular-svg-icons'
import { faExclamationTriangle, faTimesOctagon } from '@fortawesome/pro-solid-svg-icons'
import setupImg from '../../assets/img/mollie-dashboard.png'
import Button from '@webstollen/react-jtl-plugin/lib/components/Button'
import TextLink from '@webstollen/react-jtl-plugin/lib/components/TextLink'
import { Invalid, MethodProps, Valid } from './Method'

type LoadingState = {
  methods?: boolean
  statistics?: boolean
}

const Dashboard = () => {
  const [legende, setLegende] = useState(false)
  const [methods, setMethods] = useState<Record<string, Record<string, any>>>({})
  const [statistics, setStatistics] = useState<Record<string, Record<string, any>>>({})
  const [loading, _setLoading] = useState<LoadingState>({ methods: false, statistics: false })
  const [setup, setSetup] = useState(true)
  const pInfo = usePluginInfo()
  const prefix = pInfo.endpoint.substring(0, pInfo.endpoint.lastIndexOf('/')) + '/app/build'
  const api = useApi()

  const setLoading = (key: string, value: boolean) => {
    _setLoading((prev) => {
      return { ...prev, [key]: value }
    })
  }

  const loadMethods = useCallback(() => {
    setLoading('methods', true)
    api
      .run('mollie', 'methods')
      .then((res) => {
        setMethods(res.data.data)
        setSetup(false)
      })
      .catch(console.error)
      .finally(() => {
        setLoading('methods', false)
      })
  }, [api])

  const loadStatistics = useCallback(() => {
    setLoading('statistics', true)
    api
      .run('mollie', 'statistics')
      .then((res) => setStatistics(res.data.data))
      .catch(console.error)
      .finally(() => setLoading('statistics', false))
  }, [api])

  useEffect(() => {
    loadMethods()
    loadStatistics()
  }, [loadStatistics, loadMethods])

  const validMethods: React.ReactNode[] = []
  const invalidMethods: React.ReactNode[] = []

  Object.keys(methods).forEach((id) => {
    if (
      methods[id].mollie.status === 'activated' &&
      methods[id].shipping.length &&
      methods[id].paymentMethod &&
      (!methods[id].duringCheckout || methods[id].allowDuringCheckout)
    ) {
      validMethods.push(<Valid method={methods[id] as MethodProps} />)
    } else {
      invalidMethods.push(<Invalid method={methods[id] as MethodProps} />)
    }
  })

  return (
    <div className="mx-2">
      <div className="mb-4 w-full bg-white rounded-md p-4 relative">
        <FontAwesomeIcon
          onClick={loadMethods}
          spin={loading.methods}
          icon={faSync}
          size={'lg'}
          className="float-right cursor-pointer"
          title="aktualisieren"
        />

        {setup ? (
          <>
            <img src={prefix + setupImg} alt="Setup Assistant" className="mx-auto" />
            <Button
              onClick={() => (window.location.href = 'https://ws-url.de/mollie-pay')}
              color="green"
              className="mx-auto block my-6"
            >
              Jetzt kostenlos Mollie Account anlegen!
            </Button>
          </>
        ) : (
          <div className="flex items-center my-3">
            <img
              src="https://cdn.webstollen.de/plugins/ws_mollie_ws.svg"
              alt="Plugin Icon"
              className="mr-2 max-w-full"
              style={{ maxWidth: '100px' }}
            />
            <div className="text-xl">
              Integireren Sie alle wichtigen
              <br />
              Zahlungsmethoden in kürzester zeit.
            </div>
            <div>
              <Button onClick={() => window.open('https://mollie.com/dashboard', '_blank')?.focus()} className={'mx-8'}>
                Mollie Dashboard <FontAwesomeIcon icon={faExternalLink} />
              </Button>
            </div>
          </div>
        )}

        <Loading loading={loading.methods}>
          {validMethods.length > 0 && (
            <>
              <b>Aktive Zahlungsarten:</b>
              <div className="flex flex-wrap content-center justify-start flex-row mb-4">{validMethods}</div>
            </>
          )}

          {invalidMethods.length > 0 && (
            <>
              <b>Zahlungsarten mit Problemen:</b>
              <div className="flex flex-wrap content-center justify-start flex-row mb-4">{invalidMethods}</div>
            </>
          )}
        </Loading>

        <div className="text-right">
          <TextLink className={'text-xs'} onClick={() => setLegende((prev) => !prev)}>
            Legende {legende ? 'ausblenden' : 'anzeigen'}
          </TextLink>
          {legende ? (
            <div className={'m-2 p-2'}>
              <ul>
                <li className="text-xs">
                  <FontAwesomeIcon color="red" icon={faTimesOctagon} title={'Konfigurationsfehler bei Mollie'} /> =
                  Konfigurationsfehler bei Mollie
                </li>
                <li className="text-xs">
                  <FontAwesomeIcon
                    color="red"
                    size={'xs'}
                    icon={faExclamationTriangle}
                    title={'Konfigurationsfehler im Shop'}
                  />{' '}
                  = Konfigurationsfehler im Shop
                </li>
                <li className="text-xs">
                  <FontAwesomeIcon
                    color="red"
                    size={'xs'}
                    icon={faShippingFast}
                    title={'Keine Versandarten verbunden'}
                  />{' '}
                  = Keine Versandarten verbunden
                </li>
                <li className="text-xs">
                  <FontAwesomeIcon color="green" size={'xs'} icon={faShippingFast} title={'Versandarten verbunden'} /> =
                  Versandarten verbunden
                </li>
                <li className="text-xs">
                  <FontAwesomeIcon
                    color="red"
                    size={'xs'}
                    icon={faCreditCard}
                    title={'Mollie Components deaktiviert'}
                  />{' '}
                  = Mollie Components deaktiviert
                </li>
                <li className="text-xs">
                  <FontAwesomeIcon
                    color="blue"
                    size={'xs'}
                    icon={faCreditCard}
                    title={'Mollie Components aktiviert (obligatorisch)'}
                  />{' '}
                  = Mollie Components aktiviert (obligatorisch)
                </li>
                <li className="text-xs">
                  <FontAwesomeIcon
                    color="green"
                    size={'xs'}
                    icon={faCreditCard}
                    title={'Mollie Components aktiviert (optional)'}
                  />{' '}
                  = Mollie Components aktiviert (optional)
                </li>
                <li className="text-xs">
                  <FontAwesomeIcon color="green" size={'xs'} icon={faMoneyBill} title={'Payment API aktiviert'} /> =
                  Payment API aktiviert
                </li>
                <li className="text-xs">
                  <FontAwesomeIcon
                    color="green"
                    size={'xs'}
                    icon={faEnvelopeOpenDollar}
                    title={'Zahlung vor Bestellabschluss'}
                  />{' '}
                  = Zahlung vor Bestellabschluss
                </li>
                <li className="text-xs">
                  <FontAwesomeIcon
                    color="red"
                    size={'xs'}
                    icon={faEnvelopeOpenDollar}
                    title={'Fehler: Zahlung vor Bestellabschluss'}
                  />{' '}
                  = Fehler: Zahlung vor Bestellabschluss
                </li>

                <li className="text-xs">
                  <FontAwesomeIcon
                    color="green"
                    size={'xs'}
                    icon={faEnvelopeOpenText}
                    title={'Zahlung nach Bestellabschluss'}
                  />{' '}
                  = Zahlung nach Bestellabschluss
                </li>
                <li className="text-xs">
                  <FontAwesomeIcon
                    color="blue"
                    size={'xs'}
                    icon={faEnvelopeOpenText}
                    title={'Zahlung nach Bestellabschluss, zahlung vor Bestellabschluss wäre möglich!'}
                  />{' '}
                  = Zahlung nach Bestellabschluss
                </li>
              </ul>
            </div>
          ) : null}
        </div>

        <Button onClick={() => (window.location.href = 'versandarten.php')} color="blue" className="mx-auto block my-6">
          Zu den Versandarten
        </Button>
      </div>

      <div className="flex flex-row mb-3">
        <div className="bg-white rounded-md flex-1 p-4 mr-2 relative">
          <FontAwesomeIcon
            onClick={loadStatistics}
            spin={loading.statistics}
            icon={faSync}
            size={'lg'}
            className="float-right cursor-pointer"
            title="aktualisieren"
          />
          <b>Mollie Umsätze:</b>
          <Loading loading={loading.statistics}>
            <div className="flex flex-row justify-around items-baseline place-items-center my-5">
              {statistics.day && (
                <div className="flex flex-col">
                  <div className="font-semibold">{formatAmount(statistics.day.amount, 2, '€')}</div>
                  <div className="text-ws_gray-normal text-sm">last day</div>
                </div>
              )}
              {statistics.week && (
                <div className="flex flex-col">
                  <div className="font-semibold">{formatAmount(statistics.week.amount, 2, '€')}</div>
                  <div className="text-ws_gray-normal text-sm">last week</div>
                </div>
              )}
              {statistics.month && (
                <div className="flex flex-col">
                  <div className="font-semibold">{formatAmount(statistics.month.amount, 2, '€')}</div>
                  <div className="text-ws_gray-normal text-sm">last month</div>
                </div>
              )}
              {statistics.year && (
                <div className="flex flex-col">
                  <div className="font-semibold">{formatAmount(statistics.year.amount, 2, '€')}</div>
                  <div className="text-ws_gray-normal text-sm">last year</div>
                </div>
              )}
            </div>
          </Loading>
        </div>
        <div className="bg-white rounded-md flex-1 p-4 ml-2 relative">
          <FontAwesomeIcon
            onClick={loadStatistics}
            spin={loading.statistics}
            icon={faSync}
            size={'lg'}
            className="float-right cursor-pointer"
            title="aktualisieren"
          />
          <b>Mollie Transaktionen:</b>
          <Loading loading={loading.statistics}>
            <div className="flex flex-row justify-around items-baseline place-items-center my-5">
              {statistics.day && (
                <div className="flex flex-col">
                  <div className="font-semibold">{statistics.day.transactions}</div>
                  <div className="text-ws_gray-normal text-sm">last day</div>
                </div>
              )}
              {statistics.week && (
                <div className="flex flex-col">
                  <div className="font-semibold">{statistics.week.transactions}</div>
                  <div className="text-ws_gray-normal text-sm">last week</div>
                </div>
              )}
              {statistics.month && (
                <div className="flex flex-col">
                  <div className="font-semibold">{statistics.month.transactions}</div>
                  <div className="text-ws_gray-normal text-sm">last month</div>
                </div>
              )}
              {statistics.year && (
                <div className="flex flex-col">
                  <div className="font-semibold">{statistics.year.transactions}</div>
                  <div className="text-ws_gray-normal text-sm">last year</div>
                </div>
              )}
            </div>
          </Loading>
        </div>
      </div>
    </div>
  )
}

export default Dashboard
