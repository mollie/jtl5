import React, { useCallback, useState } from 'react'
import Table, { ItemTemplate } from '@webstollen/react-jtl-plugin/lib/components/Table'
import { formatAmount, Loading } from '@webstollen/react-jtl-plugin/lib'
import { molliePaymentStatusLabel } from '../../../helper'
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome'
import { faChevronDoubleDown, faChevronDoubleLeft } from '@fortawesome/pro-regular-svg-icons'
import Button from '@webstollen/react-jtl-plugin/lib/components/Button'
import useApi from '@webstollen/react-jtl-plugin/lib/hooks/useAPI'

export type RefundsProps = {
  mollie: Record<string, any>
  reload?: () => void
}

const Refunds = ({ mollie, reload }: RefundsProps) => {
  console.debug('(Refunds->render)')
  const [showRefunds, setShowRefunds] = useState(false)
  const [refundAmount, setRefundAmount] = useState(0.0)
  const [loading, setLoading] = useState(false)
  const api = useApi()

  const handleRefundAmount = useCallback(
    (amount: number) => {
      if (
        window.confirm(
          `Möchten Sie wirklich '${amount.toFixed(2)} ${mollie.amount.currency}' dieser Zahlung zurück erstatten?`
        )
      ) {
        setLoading(true)
        api
          .run('Mollie', 'refundAmount', {
            amount: amount,
            id: mollie.id,
          })
          .then((r) => {
            setRefundAmount(0)
            if (reload) {
              reload()
            }
          })
          .catch(alert)
          .finally(() => setLoading(false))
      } else {
        setRefundAmount(0)
      }
    },
    [api, setRefundAmount, setLoading, mollie.id, mollie.amount.currency, reload]
  )

  const template = {
    id: {
      header: () => 'ID',
      data: (row) => <pre>{row.id}</pre>,
    },
    status: {
      header: () => 'Status',
      data: (row) => molliePaymentStatusLabel(row.status),
    },
    description: {
      header: () => 'Description',
      data: (row) => row.description,
    },
    amount: {
      header: () => 'Amount',
      data: (row) => formatAmount(row.amount.value, 2, row.amount.currency),
    },
    created: {
      header: () => 'Created',
      data: (row) => row.createdAt,
    },
  } as Record<string, ItemTemplate<Record<string, any>>>

  return (
    <div className="mt-4">
      <h3 className="font-bold text-2xl mb-1 cursor-pointer" onClick={() => setShowRefunds((prev) => !prev)}>
        Refunds ({mollie._embedded?.refunds?.length ?? 0})
        <FontAwesomeIcon className=" float-right" icon={showRefunds ? faChevronDoubleDown : faChevronDoubleLeft} />
      </h3>
      {showRefunds && (
        <div>
          {mollie.id.substring(0, 3) === 'tr_' && (
            <Loading loading={loading}>
              <div className="flex justify-end items-stretch">
                <div className="p-2 bg-gray-400 rounded-l leading-9">Refund Amount:</div>
                <div className="p-2 bg-gray-400 leading-9">
                  <input
                    className="mx-1 text-center bg-transparent font-bold w-24"
                    step={0.01}
                    min={0}
                    value={refundAmount}
                    onChange={(e) => setRefundAmount(parseFloat(e.target.value.replace(',', '.')))}
                    type="number"
                  />
                </div>
                <div className="p-2 bg-gray-400 rounded-r">
                  <Button
                    light={refundAmount <= 0}
                    disabled={refundAmount <= 0}
                    onClick={() => handleRefundAmount(refundAmount)}
                  >
                    refund
                  </Button>
                </div>
              </div>
            </Loading>
          )}
          {mollie._embedded?.refunds?.length ? (
            <Table striped template={template} items={mollie._embedded.refunds} />
          ) : null}
        </div>
      )}
    </div>
  )
}

export default Refunds
