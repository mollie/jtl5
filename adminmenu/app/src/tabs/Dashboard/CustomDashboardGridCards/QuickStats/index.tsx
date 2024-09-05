import React, {useCallback, useEffect, useState} from "react";
import DashboardGridCard from "@webstollen/react-jtl-plugin/lib/components/DashboardPage/DashboardGridCard";
import {FontAwesomeIcon} from "@fortawesome/react-fontawesome";
import {faSync} from "@fortawesome/pro-regular-svg-icons";
import {formatAmount, Loading} from "@webstollen/react-jtl-plugin/lib";
import useApi from "@webstollen/react-jtl-plugin/lib/hooks/useAPI";

const QuickStats = () => {
    const api = useApi()

    const [isLoading, setIsLoading] = useState<boolean>(false)
    const [statistics, setStatistics] = useState<Record<string, Record<string, any>>>({})

    const loadStatistics = useCallback(() => {
        setIsLoading(true)
        api
            .run('mollie', 'statistics')
            .then((res) => setStatistics(res.data.data))
            .catch(console.error)
            .finally(() => setIsLoading(false))
    }, [api])

    useEffect(() => {
        loadStatistics()
    }, [loadStatistics])

    return (
        <DashboardGridCard>
            <div className="w-full h-full relative">{/* This div only exists to prevent shadow from affecting other DashboardGridCards due to position:relative */}
                <Loading loading={isLoading} className={"h-full"}>
                    <div className="absolute" style={{right: 15, top: 15, zIndex: 2}}>
                        <FontAwesomeIcon
                            onClick={loadStatistics}
                            spin={isLoading}
                            icon={faSync}
                            size={'lg'}
                            className="float-right cursor-pointer"
                            title="aktualisieren"
                        />
                    </div>
                    <div style={{flexDirection: "column", justifyContent: "space-between", gap: 20, height: "100%"}} className="flex gap-1">
                        <div style={{backgroundColor: "#f5f5f5"}} className="rounded-md p-2 flex-1 relative">
                            <b>Mollie Umsätze:</b>
                            <div className="flex flex-row justify-around items-baseline place-items-center my-3">
                                {statistics.day && (
                                    <div className="flex flex-col">
                                        <div
                                            className="font-semibold">{formatAmount(statistics.day.amount, 2, '€')}</div>
                                        <div className="text-ws_gray-normal text-sm">last 24h</div>
                                    </div>
                                )}
                                {statistics.week && (
                                    <div className="flex flex-col">
                                        <div
                                            className="font-semibold">{formatAmount(statistics.week.amount, 2, '€')}</div>
                                        <div className="text-ws_gray-normal text-sm">last week</div>
                                    </div>
                                )}
                                {statistics.month && (
                                    <div className="flex flex-col">
                                        <div
                                            className="font-semibold">{formatAmount(statistics.month.amount, 2, '€')}</div>
                                        <div className="text-ws_gray-normal text-sm">last month</div>
                                    </div>
                                )}
                                {statistics.year && (
                                    <div className="flex flex-col">
                                        <div
                                            className="font-semibold">{formatAmount(statistics.year.amount, 2, '€')}</div>
                                        <div className="text-ws_gray-normal text-sm">last year</div>
                                    </div>
                                )}
                            </div>
                        </div>
                        <div style={{backgroundColor: "#f5f5f5"}} className="rounded-md p-2 flex-1 relative">
                            <b>Mollie Transaktionen:</b>
                            <div className="flex flex-row justify-around items-baseline place-items-center my-3">
                                {statistics.day && (
                                    <div className="flex flex-col">
                                        <div className="font-semibold">{statistics.day.transactions}</div>
                                        <div className="text-ws_gray-normal text-sm">last 24h</div>
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
                        </div>
                    </div>
                </Loading>
            </div>
        </DashboardGridCard>
    )
}

export default QuickStats;


