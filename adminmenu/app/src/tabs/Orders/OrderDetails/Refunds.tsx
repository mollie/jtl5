import React, { useState } from 'react'
import Table, { ItemTemplate } from '@webstollen/react-jtl-plugin/lib/components/Table'
import { formatAmount } from '@webstollen/react-jtl-plugin/lib'
import { molliePaymentStatusLabel } from '../../../helper'
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome'
import { faChevronDoubleDown, faChevronDoubleLeft } from '@fortawesome/pro-regular-svg-icons'

export type RefundsProps = {
  mollie: Record<string, any>
}

const Refunds = ({ mollie }: RefundsProps) => {
  const [showRefunds, setShowRefunds] = useState(false)

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

  return mollie._embedded?.refunds?.length ? (
    <div className="mt-4">
      <h3 className="font-bold text-2xl mb-1 cursor-pointer" onClick={() => setShowRefunds((prev) => !prev)}>
        Refunds ({mollie._embedded?.refunds?.length})
        <FontAwesomeIcon className=" float-right" icon={showRefunds ? faChevronDoubleDown : faChevronDoubleLeft} />
      </h3>
      {showRefunds ? <Table striped template={template} items={mollie._embedded.refunds} /> : null}
    </div>
  ) : null
}

export default Refunds
