import React from 'react';

type Props = {
    isDashboard: boolean
    children: React.ReactNode | null
    active: boolean
}

const Wrapper = (props: Props) => {


    return props.isDashboard ?
        <div className={'container flex flex-row ' + (props.active ? '' : 'hidden')}>
            <div className={'flex-grow'}>{props.children}</div>
            <div className={'flex-grow-0 bg-white rounded-xl p-2'}>
                <div className={'flex flex-column'} style={{maxWidth: '275px'}}>
                    <div className={'my-1'}>
                        <a href='https://ws-url.de/r23' target={'_blank'}>
                            <img className={'max-width-full'} src={'https://static.dash.bar/info/r23.png'}/>
                        </a>
                    </div>
                    <div className={'my-1'}>
                        <a href='https://ws-url.de/r21' target={'_blank'}>
                            <img className={'max-width-full'} src={'https://static.dash.bar/info/r21.png'}/>
                        </a>
                    </div>
                    <div className={'my-1'}>
                        <a href='https://ws-url.de/r33' target={'_blank'}>
                            <img className={'max-width-full'} src={'https://static.dash.bar/info/r33.png'}/>
                        </a>
                    </div>
                </div>
            </div>
        </div> : <div className={'container flex flex-row ' + (props.active ? '' : 'hidden')}
                      style={{display: props.active ? 'flex' : 'none'}}>
            {props.children}
        </div>;
}
export default Wrapper;