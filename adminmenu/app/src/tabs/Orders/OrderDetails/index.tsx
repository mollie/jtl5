import React, {useEffect, useState} from "react";
import useApi from "@webstollen/react-jtl-plugin/lib/hooks/useAPI";
import Alert from "@webstollen/react-jtl-plugin/lib/components/Alert";
import {faExclamation} from "@fortawesome/pro-solid-svg-icons";
import {FontAwesomeIcon} from '@fortawesome/react-fontawesome'
import {Loading} from "@webstollen/react-jtl-plugin/lib";
import {ApiError} from "../../../helper";
import Payments from "./Payments";
import Details from "./Details";
import OrderLines from "./OrderLines";
import Shipments from "./Shipments";
import {faChevronDoubleDown, faChevronDoubleLeft, faTimes} from "@fortawesome/pro-regular-svg-icons";
import Logs from "./Logs";
import Refunds from "./Refunds";

export type OrderDetailsProps = {
    id: string
    onClose?: (event: React.MouseEvent<HTMLDivElement, MouseEvent>) => void
}
const OrderDetails = (props: OrderDetailsProps) => {

    const [data, setData] = useState<null | Record<string, any>>(null);
    const [error, setError] = useState<ApiError | null>(null);
    const [loading, setLoading] = useState(false);
    const [showLogs, setShowLogs] = useState(false);
    const [showShipments, setShowShipments] = useState(false);
    const [showPayments, setShowPayments] = useState(false);
    const [showRefunds, setShowRefunds] = useState(false);

    const api = useApi();

    useEffect(() => {
        setError(null);
        setData(null);
        if (props.id) {
            setLoading(true);
            api.run("orders", "one", {
                id: props.id
            })
                .then(res => {
                    console.log(res.data);
                    setData(res.data);
                    setError(null)
                })
                .catch(setError)
                .finally(() => setLoading(false));
        }
    }, [api, props.id]);

    if (error !== null) {
        return <div className="relative flex-col mb-3 rounded-md w-full">
            <div className="flex-row bg-black p-3 rounded-md text-white font-bold text-2xl">
                <div className="flex-grow">
                    Bestellung: {data?.order.cBestellNr} (
                    <pre className="inline text-ws_gray-light">{props.id}</pre>
                    )
                </div>
                <div onClick={props.onClose} className="cursor-pointer">
                    <FontAwesomeIcon icon={faTimes}/>
                </div>
            </div>
            <Alert variant={"error"} icon={{icon: faExclamation}}>Fehler beim laden der Bestellung
                "{props.id}": {error.message}</Alert>
        </div>;
    }

    return <div className="relative flex-col mb-3 rounded-md w-full">
        <Loading loading={loading} className="rounded-md">
            <div className="flex-row bg-black p-3 rounded-md text-white font-bold text-2xl">
                <div className="flex-grow">
                    Bestellung: <span title={data?.order.kBestellung}>{data?.order.cBestellNr}</span> (
                    <pre className="inline text-ws_gray-light">{props.id}</pre>
                    )
                </div>
                <div onClick={props.onClose} className="cursor-pointer">
                    <FontAwesomeIcon icon={faTimes}/>
                </div>
            </div>
            <div className=" rounded-md">

                {data && data.mollie && <Details mollie={data.mollie}/>}

                {data && data.mollie && <div className="mt-4">
                    <h3 className="font-bold text-2xl mb-1">Positionen</h3>
                    <OrderLines mollie={data.mollie}/>
                </div>}

                {data && data.mollie && <div className="mt-4">
                    <h3 className="font-bold text-2xl mb-1 cursor-pointer"
                        onClick={() => setShowPayments(prev => !prev)}>
                        Zahlungen ({data.mollie._embedded?.payments?.length})
                        <FontAwesomeIcon className=" float-right"
                                         icon={showPayments ? faChevronDoubleDown : faChevronDoubleLeft}/>
                    </h3>
                    {showPayments ? <Payments mollie={data.mollie}/> : null}
                </div>}

                {data && data.mollie && <div className="mt-4">
                    <h3 className="font-bold text-2xl mb-1 cursor-pointer"
                        onClick={() => setShowRefunds(prev => !prev)}>
                        Refunds ({data.mollie._embedded?.refunds?.length})
                        <FontAwesomeIcon className=" float-right"
                                         icon={showRefunds ? faChevronDoubleDown : faChevronDoubleLeft}/>
                    </h3>
                    {showRefunds ? <Refunds mollie={data.mollie}/> : null}
                </div>}

                {data && data.mollie && <div className="mt-4">
                    <h3 className="font-bold text-2xl mb-1 cursor-pointer"
                        onClick={() => setShowShipments(prev => !prev)}>
                        Lieferungen ({data.mollie._embedded?.shipments?.length})
                        <FontAwesomeIcon className=" float-right"
                                         icon={showShipments ? faChevronDoubleDown : faChevronDoubleLeft}/>
                    </h3>
                    {showShipments && data.bestellung ? <Shipments kBestellung={data.bestellung.kBestellung} mollie={data.mollie}/> : null}
                </div>}

                {data && data.logs && <div className="mt-4">
                    <h3 className="font-bold text-2xl mb-1 cursor-pointer"
                        onClick={() => setShowLogs(prev => !prev)}>
                        Log ({data.logs.length})
                        <FontAwesomeIcon className=" float-right"
                                         icon={showLogs ? faChevronDoubleDown : faChevronDoubleLeft}/>
                    </h3>
                    {showLogs ? <Logs data={data.logs}/> : null}
                </div>}

                {/*<pre style={{overflow: "scroll", maxHeight: "500px"}}>{JSON.stringify(data, null, 2)}</pre>*/}
            </div>
        </Loading>
    </div>;
}

export default OrderDetails;