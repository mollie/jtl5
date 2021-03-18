import React, {useCallback, useEffect, useState} from "react";
import Table, {ItemTemplate} from "@webstollen/react-jtl-plugin/lib/components/Table";
import {useAPI} from "@webstollen/react-jtl-plugin/lib";
import Button from "@webstollen/react-jtl-plugin/lib/components/Button";
import {FontAwesomeIcon} from "@fortawesome/react-fontawesome";
import {faShippingFast, faSync} from "@fortawesome/pro-regular-svg-icons";
import TextLink from "@webstollen/react-jtl-plugin/lib/components/TextLink";

export type ShipmentsProps = {
    kBestellung: number
    mollie: Record<string, any>
}

const Shipments = ({mollie, kBestellung}: ShipmentsProps) => {


    const api = useAPI();
    const [shipments, setShipments] = useState<null | any[]>(null);

    const loadShipments = useCallback(() => {
        setShipments(null);
        api.run('orders', 'shipments', {
            'kBestellung': kBestellung
        }).then(result => {
            console.log(result);
            setShipments(result.data);
        }).catch(err => {
            console.log(err);
        })
    }, [api, kBestellung])

    useEffect(() => {
        loadShipments();
    }, [loadShipments]);

    const syncShipping = (kLieferschein: number) => {
        api.run('shipments', 'sync', {
            kLieferschein: kLieferschein,
            orderId: mollie.id,
            kBestellung: kBestellung,
        })
            .then(resp => {
                if (resp.data.error) {
                    alert(resp.data.error.message)
                } else {
                    loadShipments()
                    console.log(resp)
                }
            })
            .catch(err => {
                alert(err.message);
            })
    }

    if (!shipments) {
        return <p>Loading...</p>;
    }
    if (!shipments.length) {
        return <p>No shipments!</p>;
    }

    const template = {
        lsNr: {
            header: () => 'LieferscheinNr',
            data: row => row.shipment ?
                <TextLink color={"green"} href={mollie._links.dashboard?.href}
                          target='_blank'>{row.cLieferscheinNr}</TextLink> : row.cLieferscheinNr
        },
        shipmentId: {
            header: () => 'Shipment ID',
            data: row => row.shipment?.cShipmentId ? row.shipment?.cShipmentId : '-'
        },
        carrier: {
            header: () => 'Carrier',
            data: row => row.shipment?.cCarrier ? row.shipment.cCarrier : 'n/a'
        },
        code: {
            header: () => 'Code',
            data: row => row.shipment?.cCode ? <TextLink color={"blue"} target={"_blank"}
                                                         href={row.shipment?.cUrl}>{row.shipment?.cCode}</TextLink> : 'n/a'
        },
        actions: {
            header: () => ' ',
            align: 'right',
            data: row => {
                const actions: React.ReactNode[] = [];
                if (mollie.status !== 'completed' && row.shipment === null) {
                    actions.push(<Button title='synchronize' onClick={() => syncShipping(row.kLieferschein)}>
                        <FontAwesomeIcon icon={faSync}/>
                    </Button>);
                }
                if (row.cUrl) {
                    actions.push(<Button><FontAwesomeIcon icon={faShippingFast}/></Button>)
                }
                return <>{actions}</>
            }
        },
    } as Record<string, ItemTemplate<Record<string, any>>>

    return <Table striped template={template} items={shipments}/>;
}

export default Shipments;