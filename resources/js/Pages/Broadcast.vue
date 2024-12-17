<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePage } from '@inertiajs/vue3';
import Peer from "simple-peer";
import { getPermissions } from "../utils";
import { ref, reactive } from 'vue';
const page = usePage();

const authUser = page.props.auth.user;
const videoStream = ref(null);
const userStream = ref(null);
const isVisibleLink = ref(false);
const roomId = ref('9kzm6dto');
const streamingUsers = ref([]);
const allPeers = reactive({});
const currentlyConnectedUser = ref(null);
const streamId = ref(Math.random().toString(36).substring(2,10));
// Echo.private(`App.Models.User.${page.props.auth.user.id}`)
//     .listen('OrderShipmentStatusUpdated', (e) => {
//         console.log(e.order);
//     });
// Echo.channel(`notifications.${page.props.auth.user.id}`)
//     .notification((data) => {
//         console.log(data);
//     });

console.log(streamId.value);

const startStream = () => {
    // microphone and camera permissions
    
    initializeStreamingChannel();
    initializeSignalAnswerChannel(); // a private channel where the broadcaster listens to incoming signalling answer
    isVisibleLink.value = true;
};
const initializeStreamingChannel = async () => {
    const stream = await getPermissions();
    videoStream.value.srcObject = stream;
    const Peer = peerCreator(stream, authUser, true)
    Peer.create();
    Peer.initEvents();
    allPeers[authUser.id] = Peer;

Echo.join(`streaming-channel.${roomId.value}`)
    .here((users) => {
        console.log('all users', users);
        
        streamingUsers.value = users;
    })
    .joining((user) => {
        console.log("New User", user);
        // if this new user is not already on the call, send your stream offer
        const joiningUserIndex = streamingUsers.value.findIndex(
            (data) => data.id === user.id
        );
        if (joiningUserIndex < 0) {
            streamingUsers.value.push(user);
            // A new user just joined the channel so signal that user
            currentlyConnectedUser.value = user.id;
            allPeers[user.id] = peerCreator(
                videoStream.value.srcObject,
                user
            )
            // Create Peer
            allPeers[user.id].create();
            // Initialize Events
            allPeers[user.id].initEvents();
        }
    })
    .leaving((user) => {
        console.log(user.name, "Left");
        // destroy peer
        allPeers[user.id].getPeer().destroy();
        // delete peer object
        delete allPeers[user.id];
        // if one leaving is the broadcaster set streamingUsers to empty array
        if (user.id === authUser.id) {
            streamingUsers.value = [];
        } else {
            // remove from streamingUsers array
            const leavingUserIndex = streamingUsers.findIndex(
            (data) => data.id === user.id
            );
            streamingUsers.value.splice(leavingUserIndex, 1);
        }
    });
};
const signalCallback = (offer, user) => {
    axios
        .post("/stream-offer", {
            broadcaster: authUser.id,
            receiver: user,
            offer,
        })
        .then((res) => {
            console.log('here', res);
        })
        .catch((err) => {
            console.log(err);
        });
};
const peerCreator = (stream, user, initiator = false) => {
    let peer;
    return {
        create: () => {
            peer = new Peer({
                initiator: initiator,
                trickle: false,
                stream: stream,
                config: {
                    iceServers: [
                        {
                            urls: ["stun:stun.stunprotocol.org", "stun:stun1.l.google.com:19302"],
                        },
                        // {
                        //     urls: [process.env.TURN_SERVER_HOST],
                        //     username: process.env.TURN_SERVER_USER,
                        //     credential: process.env.TURN_SERVER_CRED,
                        // },
                    ],
                },
            });
        },
        getPeer: () => peer,
        initEvents: () => {
            peer.on("signal", (offer) => {
                // send offer over here.
                initiator ? signalCallback(offer, user) : acceptOffer();
            });
            peer.on("stream", (stream) => {
                console.log("onStream");
                userStream.value.srcObject = stream;
            });
            peer.on("track", (track, stream) => {
                console.log("onTrack");
            });
            peer.on("connect", () => {
                console.log("Broadcaster Peer connected");
            });
            peer.on("close", (data) => {
                console.log("Broadcaster Peer closed", data);
                removeBroadcastVideo();
            });
            peer.on("error", (err) => {
                console.log(err);
                
                console.log("handle error gracefully");
            });

            initiator || initializeSignalOfferChannel(peer)
        },

    };
}
const initializeSignalAnswerChannel = () => {
Echo.private(`stream-signal-channel.${authUser.id}`)
    .listen("StreamAnswer", ({ data }) => {
            console.log("Signal Answer from private channel");
        if (data.answer.renegotiate) {
            console.log("renegotating");
        }
        if (data.answer.sdp) {
            const updatedSignal = {
                ...data.answer,
                sdp: `${data.answer.sdp}\n`,
            };
            allPeers[currentlyConnectedUser.value]
                .getPeer()
                .signal(updatedSignal);
            }
        }
    );
};

const acceptOffer =(peer, incomingOffer, broadcaster, cleanupCallback) => {
    // peer.on("signal", (data) => {
        axios
            .post("/stream-answer", {
            broadcaster,
            answer: incomingOffer,
            })
            .then((res) => {
                console.log(res);
            })
            .catch((err) => {
                console.log(err);
            });
    // });
    // peer.on("stream", (stream) => {
    //     // display remote stream
    //     videoStream.value.srcObject = stream;
    // });
    // peer.on("track", (track, stream) => {
    //     console.log("onTrack");
    // });
    // peer.on("connect", () => {
    //     console.log("Viewer Peer connected");
    // });
    // peer.on("close", () => {
    //     console.log("Viewer Peer closed");
    //     peer.destroy();
    //     cleanupCallback();
    // });
    // peer.on("error", (err) => {
    //     console.log("handle error gracefully");
    // });
    const updatedOffer = {
        ...incomingOffer,
        sdp: `${incomingOffer.sdp}\n`,
    };
    peer.signal(updatedOffer);
};

const initializeSignalOfferChannel = (peer) => {

    Echo.private(`stream-signal-channel.${authUser.id}`)
        .listen("StreamOffer", ({ data }) => {
            console.log("Signal Offer from private channel");
            // broadcasterId.value = data.broadcaster;
            acceptOffer(peer, data.offer, data.broadcaster);
        });
};

const removeBroadcastVideo = () => {
    console.log("removingBroadcast Video");
    alert("Livestream ended by broadcaster");
    const tracks = videoStream.value.srcObject.getTracks();
    tracks.forEach((track) => {
        track.stop();
    });
    videoStream.value.srcObject = null;
};
</script>

<template>
    <AppLayout title="Stream">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Stream
            </h2>
        </template>

        <div class="py-12">
            <button @click="startStream" class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Start
            </button>
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <video autoplay muted ref="videoStream"></video>
                    <video autoplay muted ref="userStream"></video>
                    <!-- <video autoplay v-for="peer in Object.values(allPeers)" :srcObject="peer.stream"></video> -->
                </div>
            </div>
        </div>
    </AppLayout>
</template>
